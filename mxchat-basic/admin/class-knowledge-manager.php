<?php
/**
 * File: admin/class-knowledge-manager.php
 * 
 * Handles all knowledge base content processing for MxChat
 * Including PDF, sitemap, content processing, and WordPress post management
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MxChat_Knowledge_Manager {
    
    private $options;
    
    /**
     * Constructor - Register hooks for content processing
     */
public function __construct() {
    $this->options = get_option('mxchat_options', array());
    $this->mxchat_init_hooks();
    
    $this->mxchat_init_role_hooks();
}
    
/**
 * Initialize WordPress hooks for content processing
 *
 */
private function mxchat_init_hooks() {
    // Admin post handlers for form submissions
    add_action('admin_post_mxchat_submit_content', array($this, 'mxchat_handle_content_submission'));
    add_action('admin_post_mxchat_submit_sitemap', array($this, 'mxchat_handle_sitemap_submission'));
    add_action('admin_post_mxchat_stop_processing', array($this, 'mxchat_stop_processing'));
    
    // AJAX handlers for real-time processing and status updates
    add_action('wp_ajax_mxchat_get_status_updates', array($this, 'mxchat_ajax_get_status_updates'));
    add_action('wp_ajax_mxchat_dismiss_completed_status', array($this, 'mxchat_ajax_dismiss_completed_status'));
    add_action('wp_ajax_mxchat_get_content_list', array($this, 'ajax_mxchat_get_content_list'));
    add_action('wp_ajax_mxchat_process_selected_content', array($this, 'ajax_mxchat_process_selected_content'));
    add_action('wp_ajax_mxchat_save_inline_prompt', array($this, 'mxchat_save_inline_prompt'));
    add_action('admin_post_mxchat_delete_pinecone_prompt', array($this, 'mxchat_handle_pinecone_prompt_delete'));
    add_action('wp_ajax_mxchat_delete_pinecone_prompt', array($this, 'ajax_mxchat_delete_pinecone_prompt'));
    add_action('wp_ajax_mxchat_delete_chunks_by_url', array($this, 'ajax_mxchat_delete_chunks_by_url'));
    add_action('wp_ajax_mxchat_delete_wordpress_prompt', array($this, 'ajax_mxchat_delete_wordpress_prompt'));
    add_action('wp_ajax_mxchat_bulk_delete_knowledge', array($this, 'ajax_mxchat_bulk_delete_knowledge'));
    add_action('wp_ajax_mxchat_update_role_restriction', array($this, 'ajax_mxchat_update_role_restriction'));
    
    //   Queue-based processing AJAX handlers
    add_action('wp_ajax_mxchat_get_next_queue_item', array($this, 'ajax_mxchat_get_next_queue_item'));
    add_action('wp_ajax_mxchat_process_queue_item', array($this, 'ajax_mxchat_process_queue_item'));
    add_action('wp_ajax_mxchat_get_queue_status', array($this, 'ajax_mxchat_get_queue_status'));
    add_action('wp_ajax_mxchat_clear_queue', array($this, 'ajax_mxchat_clear_queue'));
    add_action('wp_ajax_mxchat_retry_failed', array($this, 'ajax_mxchat_retry_failed'));
    add_action('wp_ajax_mxchat_get_recent_entries', array($this, 'ajax_mxchat_get_recent_entries'));
    add_action('wp_ajax_mxchat_detect_sitemaps', array($this, 'ajax_mxchat_detect_sitemaps'));
    add_action('wp_ajax_mxchat_refresh_pinecone_entries', array($this, 'ajax_mxchat_refresh_pinecone_entries'));
    add_action('wp_ajax_mxchat_paginate_entries', array($this, 'ajax_mxchat_paginate_entries'));

    // Hook for content deletion
    add_action('mxchat_delete_content', array($this, 'mxchat_delete_from_pinecone_by_url'), 10, 1);
    
    // WordPress post management hooks
    add_action('pre_post_update', array($this, 'mxchat_store_pre_update_status'), 10, 2);
    add_action('post_updated', array($this, 'mxchat_handle_post_update'), 10, 3);
    add_action('before_delete_post', array($this, 'mxchat_handle_post_delete'));
    add_action('wp_trash_post', array($this, 'mxchat_handle_post_delete'));

    // ACF hook - fires AFTER ACF fields are saved, ensuring ACF data is available
    // Priority 20 to run after ACF's own save (which runs at priority 10)
    add_action('acf/save_post', array($this, 'mxchat_handle_acf_save'), 20);

    add_action('wp_ajax_mxchat_mark_queue_complete', array($this, 'ajax_mxchat_mark_queue_complete'));

    // WooCommerce product hooks (if WooCommerce is active)
    if (class_exists('WooCommerce')) {
        add_action('pre_post_update', array($this, 'mxchat_store_pre_update_status'), 10, 2);
        add_action('save_post_product', array($this, 'mxchat_handle_product_change'), 10, 3);
        add_action('wp_trash_post', array($this, 'mxchat_handle_product_delete'));
        add_action('before_delete_post', array($this, 'mxchat_handle_product_delete'));
    }
}
    
    /**
     * Get current options (refreshed)
     */
    private function mxchat_get_options() {
        if (empty($this->options)) {
            $this->options = get_option('mxchat_options', array());
        }
        return $this->options;
    }
    

    // ========================================
    // MAIN CONTENT SUBMISSION HANDLERS
    // ========================================
    
public function mxchat_handle_content_submission() {
    // Check if the form was submitted and the user has permission.
    if (!isset($_POST['submit_content']) || !current_user_can('manage_options')) {
        return;
    }
    
    // Verify the nonce.
    $nonce = isset($_POST['mxchat_submit_content_nonce']) ? sanitize_text_field(wp_unslash($_POST['mxchat_submit_content_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'mxchat_submit_content_action')) {
        wp_die(esc_html__('Nonce verification failed.', 'mxchat'));
    }
    
    // Sanitize the inputs.
    // Use wp_kses_post to allow safe HTML and wp_unslash to remove WordPress added slashes
    $article_content = wp_kses_post(wp_unslash($_POST['article_content']));
    $article_url = isset($_POST['article_url']) ? esc_url_raw($_POST['article_url']) : '';
    
    //  Get bot_id from form submission
    $bot_id = isset($_POST['bot_id']) ? sanitize_key($_POST['bot_id']) : 'default';
    
    //  Get bot-specific options and API key
    $bot_options = $this->get_bot_options($bot_id);
    $options = !empty($bot_options) ? $bot_options : get_option('mxchat_options');
    $selected_model = $options['embedding_model'] ?? 'text-embedding-ada-002';
    
    if (strpos($selected_model, 'voyage') === 0) {
        $api_key = $options['voyage_api_key'] ?? '';
    } elseif (strpos($selected_model, 'gemini-embedding') === 0) {
        $api_key = $options['gemini_api_key'] ?? '';
    } else {
        $api_key = $options['api_key'] ?? '';
    }
    
    if (empty($api_key)) {
        set_transient('mxchat_admin_notice_error',
            esc_html__('API key is not configured. Please add your API key in the settings before submitting content.', 'mxchat'),
            30
        );
        wp_safe_redirect(esc_url(admin_url('admin.php?page=mxchat-prompts')));
        exit;
    }
    
    //  Use centralized utility function with bot_id
    $result = MxChat_Utils::submit_content_to_db($article_content, $article_url, $api_key, null, $bot_id);
    
    if (is_wp_error($result)) {
        set_transient('mxchat_admin_notice_error',
            esc_html__('Error storing content: ', 'mxchat') . $result->get_error_message(),
            30
        );
    } else {
        set_transient('mxchat_admin_notice_success',
            esc_html__('Content successfully submitted!', 'mxchat'),
            30
        );
    }
    
    wp_safe_redirect(esc_url(admin_url('admin.php?page=mxchat-prompts')));
    exit;
}

public function mxchat_is_pdf_url($url, $response) {
    $content_type = wp_remote_retrieve_header($response, 'content-type');
    $file_extension = strtolower(pathinfo($url, PATHINFO_EXTENSION));

    return strpos($content_type, 'pdf') !== false || $file_extension === 'pdf';
}


public function mxchat_handle_pdf_for_knowledge_base($pdf_url, $response, $bot_id = 'default') {
    if (!current_user_can('manage_options')) {
        return false;
    }

    $pdf_url = esc_url_raw($pdf_url);
    $upload_dir = wp_upload_dir();

    if (isset($upload_dir['error']) && $upload_dir['error'] !== false) {
        return false;
    }

    $pdf_filename = sanitize_file_name('mxchat_kb_' . time() . '.pdf');
    $pdf_path = trailingslashit($upload_dir['path']) . $pdf_filename;

    $response_body = wp_remote_retrieve_body($response);
    if (empty($response_body)) {
        return false;
    }

    if (!wp_mkdir_p(dirname($pdf_path))) {
        return false;
    }

    try {
        file_put_contents($pdf_path, $response_body);

        if (!file_exists($pdf_path)) {
            throw new Exception(__('Failed to save PDF file', 'mxchat'));
        }

        $total_pages = $this->mxchat_validate_and_count_pdf_pages($pdf_path);
        
        if ($total_pages === false || $total_pages < 1) {
            throw new Exception(__('Invalid PDF: Unable to parse or no pages found', 'mxchat'));
        }

        // Create unique queue ID
        $queue_id = 'pdf_' . md5($pdf_url . time());

        // Create array of pages to process
        $pages = array();
        for ($i = 1; $i <= $total_pages; $i++) {
            $pages[] = array(
                'pdf_path' => $pdf_path,
                'pdf_url' => $pdf_url,
                'page_number' => $i,
                'total_pages' => $total_pages
            );
        }

        // Add pages to queue
        $queued_count = $this->mxchat_add_to_queue($queue_id, 'pdf_page', $pages, $bot_id);

        if ($queued_count === 0) {
            wp_delete_file($pdf_path);
            throw new Exception(__('Failed to add PDF pages to processing queue', 'mxchat'));
        }

        // Store queue metadata
        $this->mxchat_set_queue_meta($queue_id, 'source_url', $pdf_url);
        $this->mxchat_set_queue_meta($queue_id, 'queue_type', 'pdf');
        $this->mxchat_set_queue_meta($queue_id, 'total_items', $total_pages);
        $this->mxchat_set_queue_meta($queue_id, 'bot_id', $bot_id);
        $this->mxchat_set_queue_meta($queue_id, 'pdf_path', $pdf_path);
        $this->mxchat_set_queue_meta($queue_id, 'created_at', current_time('mysql'));

        // Store queue ID in transient for status tracking
        set_transient('mxchat_active_queue_pdf', $queue_id, DAY_IN_SECONDS);
        set_transient('mxchat_last_pdf_url', $pdf_url, DAY_IN_SECONDS);

        return 'queued';

    } catch (Exception $e) {
        if (file_exists($pdf_path)) {
            wp_delete_file($pdf_path);
        }
        return $e->getMessage();
    }
}

/**
 *   Validate PDF and count pages with multiple parser attempts
 */
private function mxchat_validate_and_count_pdf_pages($pdf_path) {
    // Method 1: Try with Smalot PDF Parser (your current method)
    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($pdf_path);
        $pages = $pdf->getPages();
        $page_count = count($pages);
        
        if ($page_count > 0) {
            //error_log('PDF parsed successfully with Smalot parser: ' . $page_count . ' pages');
            return $page_count;
        }
    } catch (Exception $e) {
        //error_log('Smalot PDF parser failed: ' . $e->getMessage());
    }

    // Method 2: Try with pdfinfo command (if available)
    if (function_exists('shell_exec') && !$this->mxchat_is_shell_disabled()) {
        try {
            $command = 'pdfinfo ' . escapeshellarg($pdf_path) . ' 2>&1';
            $output = shell_exec($command);
            
            if ($output && preg_match('/Pages:\s*(\d+)/', $output, $matches)) {
                $page_count = intval($matches[1]);
                if ($page_count > 0) {
                    //error_log('PDF parsed successfully with pdfinfo: ' . $page_count . ' pages');
                    return $page_count;
                }
            }
        } catch (Exception $e) {
            //error_log('pdfinfo command failed: ' . $e->getMessage());
        }
    }

    // Method 3: Try to repair PDF and parse again
    try {
        $repaired_path = $this->mxchat_attempt_pdf_repair($pdf_path);
        if ($repaired_path && $repaired_path !== $pdf_path) {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($repaired_path);
            $pages = $pdf->getPages();
            $page_count = count($pages);
            
            if ($page_count > 0) {
                // Replace original with repaired version
                copy($repaired_path, $pdf_path);
                unlink($repaired_path);
                //error_log('PDF repaired and parsed successfully: ' . $page_count . ' pages');
                return $page_count;
            }
            
            // Clean up repaired file if it didn't work
            unlink($repaired_path);
        }
    } catch (Exception $e) {
        //error_log('PDF repair attempt failed: ' . $e->getMessage());
    }

    // Method 4: Manual PDF structure analysis (basic page count)
    try {
        $page_count = $this->mxchat_manual_pdf_page_count($pdf_path);
        if ($page_count > 0) {
            //error_log('PDF page count determined manually: ' . $page_count . ' pages');
            return $page_count;
        }
    } catch (Exception $e) {
        //error_log('Manual PDF analysis failed: ' . $e->getMessage());
    }

    //error_log('All PDF parsing methods failed for: ' . $pdf_path);
    return false;
}

/**
 *   Check if shell_exec is disabled
 */
private function mxchat_is_shell_disabled() {
    $disabled = explode(',', ini_get('disable_functions'));
    return in_array('shell_exec', $disabled);
}

/**
 *   Attempt to repair PDF using basic methods
 */
private function mxchat_attempt_pdf_repair($pdf_path) {
    try {
        $content = file_get_contents($pdf_path);
        if (!$content) {
            return false;
        }

        // Check if PDF starts with proper header
        if (substr($content, 0, 4) !== '%PDF') {
            // Try to find PDF header in the content
            $header_pos = strpos($content, '%PDF');
            if ($header_pos !== false && $header_pos < 1024) {
                // Remove junk before PDF header
                $content = substr($content, $header_pos);
                $repaired_path = $pdf_path . '.repaired';
                file_put_contents($repaired_path, $content);
                return $repaired_path;
            }
        }

        // Check for EOF marker
        $content = rtrim($content);
        if (!preg_match('/%%EOF\s*$/', $content)) {
            // Add EOF marker if missing
            $content .= "\n%%EOF";
            $repaired_path = $pdf_path . '.repaired';
            file_put_contents($repaired_path, $content);
            return $repaired_path;
        }

    } catch (Exception $e) {
        //error_log('PDF repair error: ' . $e->getMessage());
    }

    return false;
}

/**
 *   Manual PDF page counting by analyzing PDF structure
 */
private function mxchat_manual_pdf_page_count($pdf_path) {
    try {
        $content = file_get_contents($pdf_path);
        if (!$content) {
            return 0;
        }

        // Method 1: Count /Type /Page objects
        $page_count = preg_match_all('/\/Type\s*\/Page[^s]/', $content);
        if ($page_count > 0) {
            return $page_count;
        }

        // Method 2: Look for /Count in pages object
        if (preg_match('/\/Type\s*\/Pages.*?\/Count\s+(\d+)/', $content, $matches)) {
            return intval($matches[1]);
        }

        // Method 3: Count page references
        $page_count = preg_match_all('/\d+\s+0\s+obj\s*<<[^>]*\/Type\s*\/Page/', $content);
        if ($page_count > 0) {
            return $page_count;
        }

    } catch (Exception $e) {
        //error_log('Manual PDF analysis error: ' . $e->getMessage());
    }

    return 0;
}


public function mxchat_save_inline_prompt() {
    // DEBUG: Log what we're receiving
    //error_log('=== MXCHAT DEBUG ===');
    //error_log('POST data: ' . print_r($_POST, true));
    //error_log('Nonce from POST: ' . ($_POST['_ajax_nonce'] ?? 'NOT FOUND'));
    
    // Check for nonce security
    check_ajax_referer('mxchat_save_inline_nonce', '_ajax_nonce');
    
    // If we get here, nonce passed
    //error_log('Nonce verification PASSED');
    
    // Verify permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(esc_html__('Permission denied.', 'mxchat'));
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';
    
    // Validate and sanitize input data
    $prompt_id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $article_content = isset($_POST['article_content']) ? wp_kses_post(wp_unslash($_POST['article_content'])) : '';
    $article_url = isset($_POST['article_url']) ? esc_url_raw($_POST['article_url']) : '';
    
    if ($prompt_id > 0 && !empty($article_content)) {
        // Re-generate the embedding vector for the updated content
        $embedding_vector = $this->mxchat_generate_embedding($article_content);
        if (is_array($embedding_vector)) {
            // Serialize the embedding vector before storing it
            $embedding_vector_serialized = serialize($embedding_vector);
            // Update the prompt in the database
            $updated = $wpdb->update(
                $table_name,
                array(
                    'article_content'   => $article_content,
                    'embedding_vector'  => $embedding_vector_serialized,
                    'source_url'        => $article_url,
                ),
                array('id' => $prompt_id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            if ($updated !== false) {
                wp_send_json_success();
            } else {
                MxChat_Admin::mxchat_log_debug('knowledge_error', 'Database update failed when saving knowledge entry');
                wp_send_json_error(esc_html__('Database update failed.', 'mxchat'));
            }
        } else {
            MxChat_Admin::mxchat_log_debug('embedding_error', 'Embedding generation failed for knowledge entry');
            wp_send_json_error(esc_html__('Embedding generation failed.', 'mxchat'));
        }
    } else {
        wp_send_json_error(esc_html__('Invalid data.', 'mxchat'));
    }
}


public function mxchat_get_pdf_processing_status($pdf_url) {
    $pdf_url = esc_url_raw($pdf_url);
    $status = get_transient(sanitize_key('mxchat_pdf_status_' . md5($pdf_url)));

    if (!$status || !is_array($status)) {
        return false;
    }

    // Check for stalled processing (no updates for 5 minutes)
    if ($status['status'] === 'processing' && (time() - absint($status['last_update'])) > 300) {
        $status['status'] = 'error';
        $status['error'] = __('PDF processing appears to be stalled. No updates for over 5 minutes.', 'mxchat');
        
        // Save the updated status
        set_transient(
            sanitize_key('mxchat_pdf_status_' . md5($pdf_url)),
            array_map('sanitize_text_field', $status),
            DAY_IN_SECONDS
        );
    }

    $result = array(
        'total_pages' => absint($status['total_pages']),
        'processed_pages' => absint($status['processed_pages']),
        'failed_pages' => absint($status['failed_pages'] ?? 0),
        'percentage' => ($status['total_pages'] > 0)
            ? round((absint($status['processed_pages']) / absint($status['total_pages'])) * 100)
            : 0,
        'status' => sanitize_text_field($status['status']),
        'last_update' => human_time_diff(absint($status['last_update']), time()) . ' ' . esc_html__('ago', 'mxchat'),
        'failed_pages_list' => isset($status['failed_pages_list']) ? $status['failed_pages_list'] : array(),
        'completion_summary' => isset($status['completion_summary']) ? $status['completion_summary'] : null
    );
    
    // Add error message if present
    if (isset($status['error']) && !empty($status['error'])) {
        $result['error'] = sanitize_text_field($status['error']);
    }
    
    return $result;
}

    
public function mxchat_handle_sitemap_submission() {
    // Check if the form was submitted and verify permissions
    if (!isset($_POST['submit_sitemap']) || !current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized access', 'mxchat'));
    }

    // Verify nonce
    check_admin_referer('mxchat_submit_sitemap_action', 'mxchat_submit_sitemap_nonce');

    // Validate URL
    if (!isset($_POST['sitemap_url']) || empty($_POST['sitemap_url'])) {
        set_transient('mxchat_admin_notice_error',
            esc_html__('Please provide a valid URL.', 'mxchat'),
            30
        );
        wp_safe_redirect(esc_url(admin_url('admin.php?page=mxchat-prompts')));
        exit;
    }

    $submitted_url = esc_url_raw($_POST['sitemap_url']);
    
    // Get bot_id from form submission
    $bot_id = isset($_POST['bot_id']) ? sanitize_key($_POST['bot_id']) : 'default';

    // Get bot-specific options and validate API key
    $bot_options = $this->get_bot_options($bot_id);
    $options = !empty($bot_options) ? $bot_options : get_option('mxchat_options');
    $selected_model = $options['embedding_model'] ?? 'text-embedding-ada-002';
    
    if (strpos($selected_model, 'voyage') === 0) {
        $api_key = $options['voyage_api_key'] ?? '';
        $provider_name = 'Voyage AI';
    } elseif (strpos($selected_model, 'gemini-embedding') === 0) {
        $api_key = $options['gemini_api_key'] ?? '';
        $provider_name = 'Google Gemini';
    } else {
        $api_key = $options['api_key'] ?? '';
        $provider_name = 'OpenAI';
    }
    
    if (empty($api_key)) {
        $error_message = sprintf(
            esc_html__('%s API key is not configured. Please add your API key in the settings before submitting content.', 'mxchat'),
            $provider_name
        );
        set_transient('mxchat_admin_notice_error', $error_message, 30);
        wp_safe_redirect(esc_url(admin_url('admin.php?page=mxchat-prompts')));
        exit;
    }

    // Fetch URL
    $response = wp_remote_get($submitted_url, array('timeout' => 30));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        $error_message = is_wp_error($response) ? $response->get_error_message() : 'HTTP Status: ' . wp_remote_retrieve_response_code($response);
        set_transient('mxchat_admin_notice_error',
            sprintf(
                esc_html__('Failed to fetch the URL: %s', 'mxchat'),
                esc_html($error_message)
            ),
            30
        );
        wp_safe_redirect(esc_url(admin_url('admin.php?page=mxchat-prompts')));
        exit;
    }

    $content_type = wp_remote_retrieve_header($response, 'content-type');
    $body_content = wp_remote_retrieve_body($response);

    if (empty($body_content)) {
        set_transient('mxchat_admin_notice_error',
            esc_html__('Empty response received from URL.', 'mxchat'),
            30
        );
        wp_safe_redirect(esc_url(admin_url('admin.php?page=mxchat-prompts')));
        exit;
    }

    // Handle PDF URL
    if ($this->mxchat_is_pdf_url($submitted_url, $response)) {
        $result = $this->mxchat_handle_pdf_for_knowledge_base($submitted_url, $response, $bot_id);

        if ($result === 'queued') {
            set_transient('mxchat_admin_notice_success',
                esc_html__('PDF queued for processing. Processing will start automatically.', 'mxchat'),
                30
            );
        } else {
            set_transient('mxchat_admin_notice_error',
                esc_html__('Failed to queue PDF processing: ', 'mxchat') . esc_html($result),
                30
            );
        }

        wp_safe_redirect(esc_url(admin_url('admin.php?page=mxchat-prompts')));
        exit;
    }

    // Handle Sitemap XML
    if (strpos($content_type, 'xml') !== false || strpos($body_content, '<urlset') !== false) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body_content);
        $xml_errors = libxml_get_errors();
        libxml_clear_errors();

        if ($xml === false || !empty($xml_errors)) {
            set_transient('mxchat_admin_notice_error',
                esc_html__('Invalid sitemap XML. Please provide a valid sitemap.', 'mxchat'),
                30
            );
            wp_safe_redirect(esc_url(admin_url('admin.php?page=mxchat-prompts')));
            exit;
        }

        $result = $this->mxchat_handle_sitemap_for_knowledge_base($xml, $submitted_url, $bot_id);

        if ($result === 'queued') {
            set_transient('mxchat_admin_notice_success',
                esc_html__('Sitemap queued for processing. Processing will start automatically.', 'mxchat'),
                30
            );
        } else {
            set_transient('mxchat_admin_notice_error',
                esc_html__('Failed to queue sitemap processing. Please check the status below for details.', 'mxchat'),
                30
            );
        }

        wp_safe_redirect(esc_url(admin_url('admin.php?page=mxchat-prompts')));
        exit;
    }

    // Handle Regular URL (single page)
    $page_content = $this->mxchat_extract_main_content($body_content);
    $sanitized_content = $this->mxchat_sanitize_content_for_api($page_content);

    error_log('[MXCHAT-URL-DEBUG] URL: ' . $submitted_url);
    error_log('[MXCHAT-URL-DEBUG] Extracted page_content length: ' . strlen($page_content));
    error_log('[MXCHAT-URL-DEBUG] Sanitized content length: ' . strlen($sanitized_content));
    error_log('[MXCHAT-URL-DEBUG] Content preview: ' . substr($sanitized_content, 0, 500));

    if (empty($sanitized_content)) {
        set_transient('mxchat_admin_notice_error',
            esc_html__('No valid content found on the provided URL.', 'mxchat'),
            30
        );
        wp_safe_redirect(esc_url(admin_url('admin.php?page=mxchat-prompts')));
        exit;
    }

    // For single URLs, process immediately using submit_content_to_db
    // This handles chunking automatically for large content
    $db_result = MxChat_Utils::submit_content_to_db(
        $sanitized_content,
        $submitted_url,
        $api_key,
        null,
        $bot_id,
        'url' // content_type
    );

    if (is_wp_error($db_result)) {
        $error_message = esc_html__('Failed to store content in database: ', 'mxchat') . esc_html($db_result->get_error_message());
        set_transient('mxchat_admin_notice_error', $error_message, 30);
    } else {
        $success_message = esc_html__('URL content successfully submitted!', 'mxchat');
        set_transient('mxchat_admin_notice_success', $success_message, 30);
    }

    wp_safe_redirect(esc_url(admin_url('admin.php?page=mxchat-prompts')));
    exit;
}


public function mxchat_get_single_url_status() {
    $status = get_transient('mxchat_single_url_status');
    if (!$status) {
        return null;
    }
    
    // Add human-readable time
    if (isset($status['timestamp'])) {
        $status['human_time'] = human_time_diff(strtotime($status['timestamp']), current_time('timestamp')) . ' ' . __('ago', 'mxchat');
    }
    
    return $status;
}

public function mxchat_handle_sitemap_for_knowledge_base($xml, $sitemap_url, $bot_id = 'default') {
    if (!current_user_can('manage_options')) {
        return false;
    }

    try {
        $sitemap_url = esc_url_raw($sitemap_url);

        if (!$xml || !is_object($xml)) {
            throw new Exception(__('Invalid XML object provided', 'mxchat'));
        }

        // Get bot-specific embedding API for validation
        $bot_options = $this->get_bot_options($bot_id);
        $options = !empty($bot_options) ? $bot_options : get_option('mxchat_options');
        
        // Test the embedding API before processing
        $test_phrase = "Test embedding generation for MxChat";
        $test_result = $this->mxchat_generate_embedding($test_phrase, $bot_id);
        
        if (is_string($test_result)) {
            throw new Exception(__('Embedding API validation failed: ', 'mxchat') . $test_result);
        }
        
        if (!is_array($test_result)) {
            throw new Exception(__('Embedding API returned unexpected result type. Please check your configuration.', 'mxchat'));
        }

        // Extract URLs from sitemap
        $urls = array();
        foreach ($xml->url as $url_element) {
            $url = esc_url_raw((string)$url_element->loc);
            if ($url) {
                $urls[] = array('url' => $url);
            }
        }

        $total_urls = count($urls);

        if ($total_urls < 1) {
            throw new Exception(__('No valid URLs found in sitemap', 'mxchat'));
        }

        // Create unique queue ID
        $queue_id = 'sitemap_' . md5($sitemap_url . time());

        // Add URLs to queue
        $queued_count = $this->mxchat_add_to_queue($queue_id, 'url', $urls, $bot_id);

        if ($queued_count === 0) {
            throw new Exception(__('Failed to add URLs to processing queue', 'mxchat'));
        }

        // Store queue metadata
        $this->mxchat_set_queue_meta($queue_id, 'source_url', $sitemap_url);
        $this->mxchat_set_queue_meta($queue_id, 'queue_type', 'sitemap');
        $this->mxchat_set_queue_meta($queue_id, 'total_items', $total_urls);
        $this->mxchat_set_queue_meta($queue_id, 'bot_id', $bot_id);
        $this->mxchat_set_queue_meta($queue_id, 'created_at', current_time('mysql'));

        // Store queue ID in transient for status tracking
        set_transient('mxchat_active_queue_sitemap', $queue_id, DAY_IN_SECONDS);
        set_transient('mxchat_last_sitemap_url', $sitemap_url, DAY_IN_SECONDS);

        return 'queued';

    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log(sprintf(esc_html__('Error preparing sitemap for processing: %s', 'mxchat'), esc_html($error_message)));
        
        return $error_message;
    }
    
}

/**
 * Remove shortcode tags but preserve the content inside them
 * Example: [vc_column]Hello World[/vc_column] becomes "Hello World"
 *
 * @param string $content The content containing shortcodes
 * @return string Content with shortcode tags removed but inner content preserved
 */
private function strip_shortcode_tags_preserve_content($content) {
    // Handle nested shortcodes by running multiple passes
    $prev_content = '';
    $max_iterations = 10; // Prevent infinite loops
    $iteration = 0;
    while ($prev_content !== $content && $iteration < $max_iterations) {
        $prev_content = $content;
        // Replace paired shortcodes [tag]content[/tag] with just the content
        $content = preg_replace('/\[([a-zA-Z0-9_-]+)[^\]]*\](.*?)\[\/\1\]/s', '$2', $content);
        $iteration++;
    }
    // Remove self-closing shortcodes [tag /] or [tag attr="val" /]
    $content = preg_replace('/\[[a-zA-Z0-9_-]+[^\]]*\/\]/', '', $content);
    // Remove any remaining opening shortcode tags [tag] or [tag attr="val"]
    $content = preg_replace('/\[[a-zA-Z0-9_-]+[^\]]*\]/', '', $content);

    return $content;
}

public function mxchat_sanitize_content_for_api($content) {
    //error_log('[MXCHAT-SANITIZE] Original content preview: ' . substr($content, 0, 500) . '...');

    // Remove shortcode tags but PRESERVE content inside them
    $content = $this->strip_shortcode_tags_preserve_content($content);

    // Remove script, style tags, and HTML comments
    $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
    $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content);
    $content = preg_replace('/<!--(.|\s)*?-->/', '', $content);

    // Remove all HTML tags and decode HTML entities
    $content = wp_strip_all_tags($content);
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5);
    
    // Normalize whitespace but preserve paragraph breaks
    // First, normalize line endings to \n
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    // Replace multiple spaces/tabs with single space, but preserve newlines
    $content = preg_replace('/[ \t]+/', ' ', $content);
    // Replace 3+ newlines with 2 newlines (max 2 blank lines)
    $content = preg_replace('/\n{3,}/', "\n\n", $content);
    // Trim each line
    $lines = explode("\n", $content);
    $lines = array_map('trim', $lines);
    $content = implode("\n", $lines);
    // Final trim
    $content = trim($content);
    
    // Remove control characters (which can cause database issues) but preserve \n (0x0A) and \r (0x0D)
    $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content);
    
    // Remove NULL bytes which can cause database errors
    $content = str_replace("\0", "", $content);
    
    // Ensure valid UTF-8 encoding
    $content = wp_check_invalid_utf8($content);
    
    // Remove any extremely long strings without spaces (often garbage)
    $content = preg_replace('/\S{300,}/', ' ', $content);
    
    // Replace problematic characters that often cause database issues
    $content = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $content); // Remove emoji and other high Unicode characters
    
    // Replace any remaining potentially problematic characters with spaces
    // BUT preserve newlines by temporarily replacing them
    $content = str_replace("\n", "NEWLINE_PLACEHOLDER", $content);
    $content = preg_replace('/[^\p{L}\p{N}\p{P}\p{Z}\p{Sm}]/u', ' ', $content);
    $content = str_replace("NEWLINE_PLACEHOLDER", "\n", $content);
    
    // Limit to reasonable length if needed
    $max_length = 65000; // Just under MySQL TEXT field limit
    if (strlen($content) > $max_length) {
        $content = substr($content, 0, $max_length);
    }
    
    //error_log('[MXCHAT-SANITIZE] Sanitized content preview: ' . substr($content, 0, 500) . '...');
    return $content;
}
public function mxchat_extract_main_content($html) {
    if (empty($html)) {
        return '';
    }
    try {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true); // Suppress HTML parsing errors
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);
        
        // For debugging purposes
        $debugEnabled = true; // Set to true to enable debugging output
        $debug = function($message) use ($debugEnabled) {
            if ($debugEnabled) {
                error_log('[MXCHAT-EXTRACT-DEBUG] ' . $message);
            }
        };
        
        // Direct targeting for Gerow theme posts
        $post_text = $xpath->query('//div[contains(@class, "post-text")]');
        if ($post_text && $post_text->length > 0) {
            $debug("Found post-text directly");
            $content = '';
            foreach ($post_text as $node) {
                $content .= $dom->saveHTML($node);
            }
            if (!empty($content)) {
                $debug("Returning post-text content");
                return $content;
            }
        }
        
        // Try to get the blog details content which contains the post-text
        $blog_details = $xpath->query('//div[contains(@class, "blog-details-content")]');
        if ($blog_details && $blog_details->length > 0) {
            $debug("Found blog-details-content");
            $content = '';
            foreach ($blog_details as $node) {
                $content .= $dom->saveHTML($node);
            }
            if (!empty($content)) {
                $debug("Returning blog-details-content");
                return $content;
            }
        }
        
        // Try to get the article which contains the blog details
        $article = $xpath->query('//article[contains(@class, "blog-details-wrap")]');
        if ($article && $article->length > 0) {
            $debug("Found article with blog-details-wrap");
            $content = '';
            foreach ($article as $node) {
                $content .= $dom->saveHTML($node);
            }
            if (!empty($content)) {
                $debug("Returning article content");
                return $content;
            }
        }
        
        // Try even broader with the blog-item-wrap
        $blog_item = $xpath->query('//div[contains(@class, "blog-item-wrap")]');
        if ($blog_item && $blog_item->length > 0) {
            $debug("Found blog-item-wrap");
            $content = '';
            foreach ($blog_item as $node) {
                $content .= $dom->saveHTML($node);
            }
            if (!empty($content)) {
                $debug("Returning blog-item-wrap content");
                return $content;
            }
        }
        
        // Specific Gerow theme path
        $gerow_path = $xpath->query('//section[contains(@class, "blog-area")]//div[contains(@class, "post-text")]');
        if ($gerow_path && $gerow_path->length > 0) {
            $debug("Found Gerow theme path to post-text");
            $content = '';
            foreach ($gerow_path as $node) {
                $content .= $dom->saveHTML($node);
            }
            if (!empty($content)) {
                $debug("Returning Gerow post-text content");
                return $content;
            }
        }
        
        // Generic blog post selectors
        $selectors = [
            // Blog post specific selectors
            '//div[contains(@class, "post-text")]',
            '//article[contains(@class, "blog-post-item")]//div[contains(@class, "post-text")]',
            '//div[contains(@class, "blog-details-content")]',
            '//article[contains(@class, "blog-details-wrap")]',
            '//div[contains(@class, "entry-content")]',
            '//div[contains(@class, "blog-content")]',
            '//div[contains(@class, "blog-item-wrap")]',
            
            // More general content selectors
            '//div[contains(@class, "page__content")]',
            '//div[contains(@class, "elementor-widget-container")]',
            '//div[contains(@class, "elementor-text-editor")]',
            '//div[contains(@class, "elementor-widget-text-editor")]',
            '//*[contains(@class, "entry-content")]',
            '//*[contains(@class, "post-content")]',
            '//*[contains(@class, "article-content")]',
            '//*[@id="content"]',
            '//*[@id="main-content"]',
            '//section[contains(@class, "blog-area")]',
            '//article',
            '//main',
            '//div[contains(@class, "content")]'
        ];
        
        // First handle Elementor content - get only leaf widget containers to avoid duplicates
        $debug("Checking for Elementor content");
        // Get widget containers that are direct children of widgets (not nested inside other widget containers)
        $elementor_widgets = $xpath->query('//div[contains(@class, "elementor-widget")]//div[contains(@class, "elementor-widget-container")]');
        if ($elementor_widgets && $elementor_widgets->length > 0) {
            $debug("Found Elementor widgets");
            $seen_content = array(); // Track seen content to avoid duplicates
            $combined_content = '';
            foreach ($elementor_widgets as $widget) {
                $widget_content = $dom->saveHTML($widget);
                if (!empty($widget_content)) {
                    // Create a hash of the content to detect duplicates
                    $content_hash = md5($widget_content);
                    if (!isset($seen_content[$content_hash])) {
                        $seen_content[$content_hash] = true;
                        $combined_content .= $widget_content;
                    }
                }
            }
            if (!empty($combined_content)) {
                $debug("Returning Elementor content");
                return $combined_content;
            }
        }
        
        // Try standard selectors one by one
        foreach ($selectors as $selector) {
            $debug("Trying selector: " . $selector);
            $nodes = $xpath->query($selector);
            if ($nodes && $nodes->length > 0) {
                $debug("Found " . $nodes->length . " matches for selector: " . $selector);
                // Only take the FIRST matching node to avoid duplicate content
                // (pages often have nested or multiple containers with same class)
                $content = $dom->saveHTML($nodes->item(0));
                if (!empty($content)) {
                    $debug("Returning content from selector: " . $selector . " (first match only)");
                    return $content;
                }
            }
        }
        
        // Manual regex fallback for post-text if DOM methods fail
        $debug("Trying regex fallback");
        if (preg_match('/<div class="post-text">(.*?)<\/div>\s*<\/div>\s*<\/div>/s', $html, $matches)) {
            $debug("Found post-text via regex");
            return '<div class="post-text">' . $matches[1] . '</div>';
        }
        
        // Try to extract the blog section as a whole
        $blog_section = $xpath->query('//section[contains(@class, "blog-area")]');
        if ($blog_section && $blog_section->length > 0) {
            $debug("Found blog-area section");
            $content = '';
            foreach ($blog_section as $node) {
                $content .= $dom->saveHTML($node);
            }
            if (!empty($content)) {
                $debug("Returning blog-area section content");
                return $content;
            }
        }

        // Generic container selectors for non-CMS sites (like .asp pages)
        $debug("Trying generic container selectors");
        $generic_selectors = [
            '//div[@id="main"]',
            '//div[@id="wrapper"]',
            '//div[@id="page"]',
            '//div[@id="site-content"]',
            '//div[contains(@class, "main-content")]',
            '//div[contains(@class, "page-content")]',
            '//div[contains(@class, "site-content")]',
        ];

        foreach ($generic_selectors as $selector) {
            $debug("Trying generic selector: " . $selector);
            $nodes = $xpath->query($selector);
            if ($nodes && $nodes->length > 0) {
                $content = $dom->saveHTML($nodes->item(0));
                if (!empty($content)) {
                    $debug("Returning content from generic selector: " . $selector);
                    return $content;
                }
            }
        }

        // Paragraph-based content detection - find regions with substantial text
        $debug("Trying paragraph-based content detection");
        $paragraphs = $xpath->query('//p[string-length(normalize-space()) > 50]');
        if ($paragraphs && $paragraphs->length >= 3) {
            $debug("Found " . $paragraphs->length . " substantial paragraphs");
            // Collect all substantial paragraphs and their content
            $paragraph_content = '';
            foreach ($paragraphs as $p) {
                $paragraph_content .= $dom->saveHTML($p) . "\n";
            }
            if (!empty($paragraph_content)) {
                $debug("Returning paragraph-based content");
                return $paragraph_content;
            }
        }

        // Improved body fallback - strip nav/header/footer elements first
        $debug("Using improved body fallback");
        $body = $dom->getElementsByTagName('body');
        if ($body->length > 0) {
            // Clone the body to avoid modifying the original DOM
            $body_clone = $body->item(0)->cloneNode(true);

            // Remove common non-content elements by tag name
            $remove_tags = ['nav', 'header', 'footer', 'aside', 'script', 'style', 'noscript'];
            foreach ($remove_tags as $tag) {
                $elements = $body_clone->getElementsByTagName($tag);
                // Iterate backwards to safely remove elements
                for ($i = $elements->length - 1; $i >= 0; $i--) {
                    $el = $elements->item($i);
                    if ($el && $el->parentNode) {
                        $el->parentNode->removeChild($el);
                    }
                }
            }

            // Remove elements with common non-content class names using XPath on the cloned body
            $temp_dom = new DOMDocument();
            @$temp_dom->appendChild($temp_dom->importNode($body_clone, true));
            $temp_xpath = new DOMXPath($temp_dom);

            $remove_class_patterns = [
                '//*[contains(@class, "nav")]',
                '//*[contains(@class, "menu")]',
                '//*[contains(@class, "sidebar")]',
                '//*[contains(@class, "footer")]',
                '//*[contains(@class, "header")]',
                '//*[contains(@id, "nav")]',
                '//*[contains(@id, "menu")]',
                '//*[contains(@id, "sidebar")]',
                '//*[contains(@id, "footer")]',
                '//*[contains(@id, "header")]',
            ];

            foreach ($remove_class_patterns as $pattern) {
                $elements = $temp_xpath->query($pattern);
                if ($elements) {
                    for ($i = $elements->length - 1; $i >= 0; $i--) {
                        $el = $elements->item($i);
                        if ($el && $el->parentNode) {
                            $el->parentNode->removeChild($el);
                        }
                    }
                }
            }

            $cleaned_content = $temp_dom->saveHTML();
            if (!empty($cleaned_content)) {
                $debug("Returning cleaned body content");
                return $cleaned_content;
            }
        }

        // Last resort: return the original HTML
        $debug("Returning original HTML");
        return $html;
    } catch (Exception $e) {
        //error_log('[MXCHAT-ERROR] Content extraction failed: ' . $e->getMessage());
        return $html; // Return original HTML if parsing fails
    } finally {
        libxml_clear_errors();
    }
}
public function mxchat_get_sitemap_processing_status($sitemap_url) {
    $sitemap_url = esc_url_raw($sitemap_url);
    $status_key = sanitize_key('mxchat_sitemap_status_' . md5($sitemap_url));
    $status = get_transient($status_key);
    
    if (!$status || !is_array($status)) {
        return false;
    }
    
    // Auto-complete check: if all URLs are processed but status isn't complete
    if (isset($status['processed_urls']) && isset($status['total_urls']) && 
        $status['processed_urls'] >= $status['total_urls'] && 
        isset($status['status']) && $status['status'] !== 'complete' && 
        $status['status'] !== 'error') {
        
        // Mark as complete
        $status['status'] = 'complete';
        $status['processed_urls'] = $status['total_urls']; // Ensure exact match
        
        // Update the transient with the corrected status
        set_transient($status_key, $status, DAY_IN_SECONDS);
    }
    
    return array(
        'total_urls' => absint($status['total_urls']),
        'processed_urls' => absint($status['processed_urls']),
        'failed_urls' => absint($status['failed_urls'] ?? 0),
        'percentage' => ($status['total_urls'] > 0)
            ? round((absint($status['processed_urls']) / absint($status['total_urls'])) * 100)
            : 0,
        'status' => sanitize_text_field($status['status']),
        'last_update' => human_time_diff(absint($status['last_update']), time()) . ' ' . esc_html__('ago', 'mxchat'),
        'error' => isset($status['error']) ? sanitize_text_field($status['error']) : '',
        'last_error' => isset($status['last_error']) ? sanitize_text_field($status['last_error']) : '',
        'failed_urls_list' => isset($status['failed_urls_list']) ? $status['failed_urls_list'] : array()
    );
}

public function mxchat_ajax_get_status_updates() {
    try {
        // Verify the request
        check_ajax_referer('mxchat_status_nonce', 'nonce');
        
        // Get active queue IDs
        $sitemap_queue_id = get_transient('mxchat_active_queue_sitemap');
        $pdf_queue_id = get_transient('mxchat_active_queue_pdf');
        
        $sitemap_status = false;
        $pdf_status = false;
        
        // Get sitemap queue status
        if ($sitemap_queue_id) {
            $sitemap_status = $this->mxchat_get_queue_status_data($sitemap_queue_id, 'sitemap');
        }
        
        // Get PDF queue status
        if ($pdf_queue_id) {
            $pdf_status = $this->mxchat_get_queue_status_data($pdf_queue_id, 'pdf');
        }
        
        $is_active_processing = 
            ($sitemap_status && $sitemap_status['status'] === 'processing') || 
            ($pdf_status && $pdf_status['status'] === 'processing');
        
        // Return JSON response with the status data
        wp_send_json(array(
            'pdf_status' => $pdf_status,
            'sitemap_status' => $sitemap_status,
            'is_processing' => $is_active_processing,
            'sitemap_queue_id' => $sitemap_queue_id,
            'pdf_queue_id' => $pdf_queue_id
        ));
        
    } catch (Exception $e) {
        error_log('MxChat Status Update Error: ' . $e->getMessage());
        
        wp_send_json_error(array(
            'message' => 'Error getting status updates: ' . $e->getMessage(),
            'status' => 'error'
        ));
    }
}

/**
 * Helper function to get queue status data
 */
private function mxchat_get_queue_status_data($queue_id, $type = 'sitemap') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_processing_queue';
    
    // Get counts by status
    $counts = $wpdb->get_results($wpdb->prepare(
        "SELECT status, COUNT(*) as count 
        FROM $table_name 
        WHERE queue_id = %s 
        GROUP BY status",
        $queue_id
    ), OBJECT_K);
    
    $total = 0;
    $completed = 0;
    $failed = 0;
    $processing = 0;
    $pending = 0;
    
    foreach ($counts as $status => $data) {
        $count = absint($data->count);
        $total += $count;
        
        switch ($status) {
            case 'completed':
                $completed = $count;
                break;
            case 'failed':
                $failed = $count;
                break;
            case 'processing':
                $processing = $count;
                break;
            case 'pending':
                $pending = $count;
                break;
        }
    }
    
    if ($total === 0) {
        return false;
    }
    
    // Calculate percentage
    $percentage = round((($completed + $failed) / $total) * 100);
    
    // Get failed items details (limit to 50)
    $failed_items = array();
    if ($failed > 0) {
        $failed_results = $wpdb->get_results($wpdb->prepare(
            "SELECT item_type, item_data, error_message, attempts, completed_at 
            FROM $table_name 
            WHERE queue_id = %s 
            AND status = 'failed'
            AND attempts >= max_attempts
            ORDER BY id DESC
            LIMIT 50",
            $queue_id
        ));
        
        foreach ($failed_results as $item) {
            $data = json_decode($item->item_data, true);
            $url = $type === 'pdf' ? ($data['pdf_url'] ?? '') : ($data['url'] ?? '');
            
            $failed_items[] = array(
                'url' => $url,
                'page' => $type === 'pdf' ? ($data['page_number'] ?? 0) : 0,
                'error' => $item->error_message,
                'retries' => $item->attempts,
                'time' => strtotime($item->completed_at)
            );
        }
    }
    
    // Get queue metadata
    $source_url = $this->mxchat_get_queue_meta($queue_id, 'source_url');
    
    // Determine if queue is complete
    $is_complete = ($pending === 0 && $processing === 0);
    
    // Get last update time
    $last_update = $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(UNIX_TIMESTAMP(COALESCE(completed_at, started_at, created_at))) 
        FROM $table_name 
        WHERE queue_id = %s",
        $queue_id
    ));
    
    $last_update_text = $last_update ? human_time_diff($last_update, time()) . ' ' . __('ago', 'mxchat') : __('Just now', 'mxchat');
    
    // Format based on type
    if ($type === 'pdf') {
        return array(
            'total_pages' => $total,
            'processed_pages' => $completed + $failed,
            'failed_pages' => $failed,
            'percentage' => $percentage,
            'status' => $is_complete ? 'complete' : 'processing',
            'last_update' => $last_update_text,
            'failed_pages_list' => $failed_items,
            'pdf_url' => $source_url,
            'queue_id' => $queue_id
        );
    } else {
        return array(
            'total_urls' => $total,
            'processed_urls' => $completed + $failed,
            'failed_urls' => $failed,
            'percentage' => $percentage,
            'status' => $is_complete ? 'complete' : 'processing',
            'last_update' => $last_update_text,
            'failed_urls_list' => $failed_items,
            'sitemap_url' => $source_url,
            'queue_id' => $queue_id
        );
    }
}

/**
 * Public method to get processing status for both sitemap and PDF queues
 * Used by admin pages to display processing status
 *
 * @return array Array with 'sitemap_status', 'pdf_status', and 'is_processing' keys
 */
public function mxchat_get_processing_statuses() {
    $sitemap_queue_id = get_transient('mxchat_active_queue_sitemap');
    $pdf_queue_id = get_transient('mxchat_active_queue_pdf');

    $sitemap_status = false;
    $pdf_status = false;

    if ($sitemap_queue_id) {
        $sitemap_status = $this->mxchat_get_queue_status_data($sitemap_queue_id, 'sitemap');
    }

    if ($pdf_queue_id) {
        $pdf_status = $this->mxchat_get_queue_status_data($pdf_queue_id, 'pdf');
    }

    $is_processing =
        ($sitemap_status && $sitemap_status['status'] === 'processing') ||
        ($pdf_status && $pdf_status['status'] === 'processing');

    return array(
        'sitemap_status' => $sitemap_status,
        'pdf_status' => $pdf_status,
        'is_processing' => $is_processing
    );
}

/**
 * AJAX handler to get recent knowledge entries for real-time table updates
 * UPDATED: Now supports both WordPress DB and Pinecone data sources
 */
public function ajax_mxchat_get_recent_entries() {
    check_ajax_referer('mxchat_entries_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';

    // Get parameters
    $last_id = isset($_POST['last_id']) ? absint($_POST['last_id']) : 0;
    $limit = isset($_POST['limit']) ? min(absint($_POST['limit']), 50) : 10;
    $bot_id = isset($_POST['bot_id']) ? sanitize_text_field($_POST['bot_id']) : 'default';

    // Check if Pinecone is enabled for this bot
    $pinecone_manager = $this->mxchat_get_pinecone_manager();
    $pinecone_options = $pinecone_manager ? $pinecone_manager->mxchat_get_bot_pinecone_options($bot_id) : array();
    $use_pinecone = ($pinecone_options['mxchat_use_pinecone'] ?? '0') === '1';
    $has_pinecone_api = !empty($pinecone_options['mxchat_pinecone_api_key']);

    if ($use_pinecone && $has_pinecone_api) {
        // PINECONE DATA SOURCE - Get grouped entry count (not raw vector count)
        // Use mxchat_fetch_pinecone_records which returns total_unique_entries
        $records = $pinecone_manager->mxchat_fetch_pinecone_records($pinecone_options, '', 1, 10, $bot_id, '');
        $total_count = $records['total'] ?? 0;

        // For Pinecone, we don't return individual entries during polling
        // (entries are already displayed on page load via mxchat_fetch_pinecone_records)
        // We just return the updated count
        wp_send_json_success(array(
            'entries' => array(),
            'total_count' => absint($total_count),
            'max_id' => $last_id,
            'data_source' => 'pinecone'
        ));
        return;
    }

    // WORDPRESS DB DATA SOURCE
    // Build query to get entries newer than last_id
    $where_clauses = array('1=1');
    $where_values = array();

    if ($last_id > 0) {
        $where_clauses[] = 'id > %d';
        $where_values[] = $last_id;
    }

    // Note: WordPress DB table doesn't have bot_id column
    // Multi-bot filtering is handled via Pinecone namespaces

    $where_sql = implode(' AND ', $where_clauses);

    // Get recent entries
    $query = "SELECT id, article_content, source_url, timestamp
              FROM $table_name
              WHERE $where_sql
              ORDER BY id DESC
              LIMIT %d";

    $where_values[] = $limit;

    $entries = $wpdb->get_results($wpdb->prepare($query, $where_values));

    // Get total count of GROUPED entries (by source_url) - matches pagination display
    // Count unique source_urls (excluding mxchat:// internal refs) + count of ungrouped rows
    $total_count = $wpdb->get_var(
        "SELECT (SELECT COUNT(DISTINCT source_url) FROM {$table_name} WHERE source_url != '' AND source_url NOT LIKE 'mxchat://%') +
                (SELECT COUNT(*) FROM {$table_name} WHERE source_url = '' OR source_url IS NULL OR source_url LIKE 'mxchat://%')"
    );

    // Format entries for response
    $formatted_entries = array();
    $preview_length = 150;
    foreach ($entries as $entry) {
        // Parse chunk metadata using the proper chunker method (same as initial page load)
        if (class_exists('MxChat_Chunker')) {
            $chunk_meta = MxChat_Chunker::parse_stored_chunk($entry->article_content);
            $display_content = $chunk_meta['text'];
            $chunk_metadata = $chunk_meta['metadata'];
        } else {
            $display_content = $entry->article_content;
            $chunk_metadata = array();
        }

        $content_preview = mb_strlen($display_content) > $preview_length
            ? mb_substr($display_content, 0, $preview_length) . '...'
            : $display_content;

        $formatted_entries[] = array(
            'id' => $entry->id,
            'preview' => esc_html($content_preview),
            'full_content' => wp_kses_post(wpautop($display_content)),
            'content_length' => mb_strlen($display_content),
            'preview_length' => $preview_length,
            'source_url' => $entry->source_url,
            'has_link' => !empty($entry->source_url) && strpos($entry->source_url, 'mxchat://') !== 0,
            'chunk_metadata' => $chunk_metadata,
            'delete_nonce' => wp_create_nonce('mxchat_delete_prompt_nonce')
        );
    }

    wp_send_json_success(array(
        'entries' => $formatted_entries,
        'total_count' => absint($total_count),
        'max_id' => !empty($entries) ? $entries[0]->id : $last_id,
        'data_source' => 'wordpress'
    ));
}

/**
 * Get Pinecone total count from stats API
 * Helper function for ajax_mxchat_get_recent_entries
 */
private function mxchat_get_pinecone_count_from_stats($pinecone_options) {
    $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
    $host = $pinecone_options['mxchat_pinecone_host'] ?? '';
    $namespace = $pinecone_options['mxchat_pinecone_namespace'] ?? '';

    if (empty($api_key) || empty($host)) {
        return 0;
    }

    try {
        $stats_url = "https://{$host}/describe_index_stats";

        $response = wp_remote_post($stats_url, array(
            'headers' => array(
                'Api-Key' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => '{}',
            'timeout' => 10
        ));

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            $stats_data = json_decode($body, true);

            // If namespace is specified, get count from that specific namespace
            if (!empty($namespace) && isset($stats_data['namespaces'][$namespace]['vectorCount'])) {
                return intval($stats_data['namespaces'][$namespace]['vectorCount']);
            }

            // If no namespace specified or namespace not found in response, use total
            return intval($stats_data['totalVectorCount'] ?? 0);
        }

        return 0;

    } catch (Exception $e) {
        return 0;
    }
}

/**
 * AJAX handler to refresh Pinecone entries table via AJAX
 * Returns the table HTML for updating the UI without a full page reload
 */
public function ajax_mxchat_refresh_pinecone_entries() {
    check_ajax_referer('mxchat_entries_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }

    $bot_id = isset($_POST['bot_id']) ? sanitize_text_field($_POST['bot_id']) : 'default';
    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $per_page = 10;

    // Get Pinecone manager and options
    $pinecone_manager = $this->mxchat_get_pinecone_manager();
    if (!$pinecone_manager) {
        wp_send_json_error(array('message' => 'Pinecone manager not available'));
        return;
    }

    $pinecone_options = $pinecone_manager->mxchat_get_bot_pinecone_options($bot_id);
    $use_pinecone = ($pinecone_options['mxchat_use_pinecone'] ?? '0') === '1';
    $pinecone_api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';

    if (!$use_pinecone || empty($pinecone_api_key)) {
        wp_send_json_error(array('message' => 'Pinecone not configured'));
        return;
    }

    // Fetch records from Pinecone
    $records = $pinecone_manager->mxchat_fetch_pinecone_records($pinecone_options, '', $page, $per_page, $bot_id, '');
    $prompts = $records['data'] ?? array();
    $total_records = $records['total'] ?? 0;

    // Group prompts by source_url
    $grouped_prompts = array();
    foreach ($prompts as $prompt) {
        $source_url = '';
        if (!empty($prompt->chunk_metadata['source_url'])) {
            $source_url = $prompt->chunk_metadata['source_url'];
        } elseif (!empty($prompt->source_url)) {
            $source_url = $prompt->source_url;
        }

        if (!empty($source_url)) {
            if (!isset($grouped_prompts[$source_url])) {
                $grouped_prompts[$source_url] = array();
            }
            $grouped_prompts[$source_url][] = $prompt;
        } else {
            $grouped_prompts['_ungrouped_' . $prompt->id] = array($prompt);
        }
    }

    // Sort each group by chunk_index
    foreach ($grouped_prompts as $source_url => &$group) {
        usort($group, function($a, $b) {
            $index_a = isset($a->chunk_metadata['chunk_index']) ? intval($a->chunk_metadata['chunk_index']) : 0;
            $index_b = isset($b->chunk_metadata['chunk_index']) ? intval($b->chunk_metadata['chunk_index']) : 0;
            return $index_a - $index_b;
        });
    }
    unset($group);

    // Build HTML for the table rows - must match admin-knowledge-page.php structure exactly
    ob_start();
    $display_index = 0;
    $current_page = $page;
    $data_source = 'pinecone';
    $current_bot_id = $bot_id;
    $preview_length = 150;

    if (empty($grouped_prompts)) {
        echo '<tr><td colspan="4" style="padding: 40px; text-align: center; color: var(--mxch-text-muted);">';
        esc_html_e('No knowledge entries found in Pinecone.', 'mxchat');
        echo '</td></tr>';
    } else {
        foreach ($grouped_prompts as $source_url => $group) {
            $chunk_count = count($group);
            $first_prompt = $group[0];
            $display_index++;

            if ($chunk_count > 1) {
                // Multiple chunks - show grouped row with expand button
                $group_id = 'group-' . md5($source_url);
                ?>
                <tr id="prompt-<?php echo esc_attr($first_prompt->id); ?>"
                    class="mxchat-chunk-group-header"
                    data-source="<?php echo esc_attr($data_source); ?>"
                    data-group-id="<?php echo esc_attr($group_id); ?>"
                    style="border-bottom: 1px solid var(--mxch-card-border); background: rgba(33, 150, 243, 0.02);">
                    <td style="padding: 12px 16px; font-size: 13px;">
                        <?php echo esc_html($display_index + (($current_page - 1) * $per_page)); ?>
                    </td>
                    <td class="mxchat-content-cell" style="padding: 12px 16px; font-size: 13px;">
                        <div class="mxchat-chunk-group-info">
                            <button type="button" class="mxchat-chunk-toggle" data-group-id="<?php echo esc_attr($group_id); ?>">
                                <span class="dashicons dashicons-arrow-right-alt2"></span>
                            </button>
                            <span class="mxchat-chunk-badge"><?php echo esc_html($chunk_count); ?> <?php esc_html_e('chunks', 'mxchat'); ?></span>
                            <span class="mxchat-chunk-preview">
                                <?php
                                $parent_content = isset($first_prompt->display_content) ? $first_prompt->display_content : (isset($first_prompt->article_content) ? $first_prompt->article_content : '');
                                $content_preview = mb_substr($parent_content, 0, 100);
                                echo esc_html($content_preview . '...');
                                ?>
                            </span>
                        </div>
                    </td>
                    <td class="mxchat-url-cell" style="padding: 12px 16px; font-size: 13px;">
                        <?php if (!empty($source_url) && strpos($source_url, 'mxchat://') !== 0 && strpos($source_url, '_ungrouped_') !== 0) : ?>
                            <a href="<?php echo esc_url($source_url); ?>" target="_blank" style="color: var(--mxch-primary); text-decoration: none;">
                                <span class="dashicons dashicons-external" style="font-size: 14px;"></span>
                                <?php esc_html_e('View Source', 'mxchat'); ?>
                            </a>
                        <?php else : ?>
                            <span style="color: var(--mxch-text-muted);"><?php esc_html_e('Manual Content', 'mxchat'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="mxchat-actions-cell" style="padding: 12px 16px;">
                        <button type="button"
                                class="mxch-btn mxch-btn-ghost mxch-btn-sm delete-button-group"
                                data-source-url="<?php echo esc_attr($source_url); ?>"
                                data-chunk-count="<?php echo esc_attr($chunk_count); ?>"
                                data-data-source="<?php echo esc_attr($data_source); ?>"
                                data-bot-id="<?php echo esc_attr($current_bot_id); ?>"
                                data-nonce="<?php echo wp_create_nonce('mxchat_delete_chunks_nonce'); ?>"
                                style="color: var(--mxch-error);"
                                title="<?php esc_attr_e('Delete all chunks', 'mxchat'); ?>">
                            <span class="dashicons dashicons-trash" style="font-size: 14px;"></span>
                        </button>
                    </td>
                </tr>
                <?php
                // Render hidden chunk rows
                foreach ($group as $chunk_index => $chunk) {
                    $meta_chunk_index = isset($chunk->chunk_metadata['chunk_index']) ? intval($chunk->chunk_metadata['chunk_index']) : $chunk_index;
                    $meta_total_chunks = isset($chunk->chunk_metadata['total_chunks']) ? intval($chunk->chunk_metadata['total_chunks']) : $chunk_count;
                    $content = isset($chunk->display_content) ? $chunk->display_content : (isset($chunk->article_content) ? $chunk->article_content : '');
                    $content_preview = mb_strlen($content) > $preview_length
                        ? mb_substr($content, 0, $preview_length) . '...'
                        : $content;
                    ?>
                    <tr id="prompt-<?php echo esc_attr($chunk->id); ?>"
                        class="mxchat-chunk-row <?php echo esc_attr($group_id); ?>"
                        data-source="<?php echo esc_attr($data_source); ?>"
                        style="display: none; background: #f8f9fa; border-bottom: 1px solid var(--mxch-card-border);">
                        <td style="padding: 12px 16px; text-align: center;">
                            <!-- Checkbox column placeholder for chunks (managed by group) -->
                        </td>
                        <td style="padding: 12px 16px 12px 30px; font-size: 13px;">
                            <!-- Hidden ID column for chunks -->
                        </td>
                        <td class="mxchat-content-cell" style="padding: 12px 16px; font-size: 13px;">
                            <div class="mxchat-accordion-wrapper">
                                <div class="mxchat-content-preview">
                                    <span class="mxchat-chunk-indicator" style="margin-right: 10px; color: var(--mxch-text-secondary); font-size: 12px;">
                                        <?php printf(esc_html__('Chunk %d of %d', 'mxchat'), $meta_chunk_index + 1, $meta_total_chunks); ?>
                                    </span>
                                    <span class="preview-text"><?php echo esc_html($content_preview); ?></span>
                                    <?php if (mb_strlen($content) > $preview_length) : ?>
                                        <button class="mxchat-expand-toggle" type="button">
                                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="mxchat-content-full" style="display: none;">
                                    <div class="content-view">
                                        <?php
                                        if (preg_match('/[\x{0590}-\x{05FF}]/u', $content)) {
                                            echo '<div dir="rtl" lang="he" class="rtl-content">';
                                            echo wp_kses_post(wpautop($content));
                                            echo '</div>';
                                        } else {
                                            echo wp_kses_post(wpautop($content));
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="mxchat-url-cell" style="padding: 12px 16px; font-size: 13px;">
                            <span class="mxchat-chunk-label" style="color: var(--mxch-text-muted);"><?php esc_html_e('Same as parent', 'mxchat'); ?></span>
                        </td>
                        <td class="mxchat-actions-cell" style="padding: 12px 16px;">
                            <span class="mxchat-chunk-label" style="color: var(--mxch-text-muted);"><?php esc_html_e('Managed by group', 'mxchat'); ?></span>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                // Single entry - display normally with accordion
                $prompt = $first_prompt;
                $content = isset($prompt->display_content) ? $prompt->display_content : (isset($prompt->article_content) ? $prompt->article_content : '');
                $content_preview = mb_strlen($content) > $preview_length
                    ? mb_substr($content, 0, $preview_length) . '...'
                    : $content;
                ?>
                <tr id="prompt-<?php echo esc_attr($prompt->id); ?>"
                    data-source="<?php echo esc_attr($data_source); ?>"
                    style="border-bottom: 1px solid var(--mxch-card-border); background: rgba(33, 150, 243, 0.02);">
                    <td style="padding: 12px 16px; font-size: 13px;">
                        <?php echo esc_html($display_index + (($current_page - 1) * $per_page)); ?>
                    </td>
                    <td class="mxchat-content-cell" style="padding: 12px 16px; font-size: 13px;">
                        <div class="mxchat-accordion-wrapper">
                            <div class="mxchat-content-preview">
                                <span class="preview-text"><?php echo esc_html($content_preview); ?></span>
                                <?php if (mb_strlen($content) > $preview_length) : ?>
                                    <button class="mxchat-expand-toggle" type="button">
                                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="mxchat-content-full" style="display: none;">
                                <div class="content-view">
                                    <?php
                                    if (preg_match('/[\x{0590}-\x{05FF}]/u', $content)) {
                                        echo '<div dir="rtl" lang="he" class="rtl-content">';
                                        echo wp_kses_post(wpautop($content));
                                        echo '</div>';
                                    } else {
                                        echo wp_kses_post(wpautop($content));
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="mxchat-url-cell" style="padding: 12px 16px; font-size: 13px;">
                        <?php
                        $actual_source = $source_url;
                        if (strpos($source_url, '_ungrouped_') === 0) {
                            $actual_source = $prompt->source_url ?? '';
                        }
                        if (!empty($actual_source) && strpos($actual_source, 'mxchat://') !== 0) : ?>
                            <a href="<?php echo esc_url($actual_source); ?>" target="_blank" style="color: var(--mxch-primary); text-decoration: none;">
                                <span class="dashicons dashicons-external" style="font-size: 14px;"></span>
                                <?php esc_html_e('View', 'mxchat'); ?>
                            </a>
                        <?php else : ?>
                            <span style="color: var(--mxch-text-muted);"><?php esc_html_e('Manual', 'mxchat'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px 16px;">
                        <button type="button" class="mxch-btn mxch-btn-ghost mxch-btn-sm delete-button-ajax" data-vector-id="<?php echo esc_attr($prompt->id); ?>" data-bot-id="<?php echo esc_attr($current_bot_id); ?>" data-nonce="<?php echo wp_create_nonce('mxchat_delete_pinecone_prompt_nonce'); ?>" style="color: var(--mxch-error);">
                            <span class="dashicons dashicons-trash" style="font-size: 14px;"></span>
                        </button>
                    </td>
                </tr>
                <?php
            }
        }
    }
    $html = ob_get_clean();

    // Generate pagination HTML for Pinecone
    $total_pages = ceil($total_records / $per_page);
    $pagination_html = '';
    if ($total_pages > 1) {
        $pagination_html = '<div class="mxchat-ajax-pagination" data-current-page="' . esc_attr($page) . '" data-total-pages="' . esc_attr($total_pages) . '">';

        // Previous button
        if ($page > 1) {
            $pagination_html .= '<a href="#" class="mxchat-page-link" data-page="' . ($page - 1) . '">' . esc_html__('&laquo; Previous', 'mxchat') . '</a> ';
        }

        // Page numbers
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);

        if ($start_page > 1) {
            $pagination_html .= '<a href="#" class="mxchat-page-link" data-page="1">1</a> ';
            if ($start_page > 2) {
                $pagination_html .= '<span class="mxchat-page-dots">...</span> ';
            }
        }

        for ($i = $start_page; $i <= $end_page; $i++) {
            if ($i == $page) {
                $pagination_html .= '<span class="mxchat-page-current">' . $i . '</span> ';
            } else {
                $pagination_html .= '<a href="#" class="mxchat-page-link" data-page="' . $i . '">' . $i . '</a> ';
            }
        }

        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                $pagination_html .= '<span class="mxchat-page-dots">...</span> ';
            }
            $pagination_html .= '<a href="#" class="mxchat-page-link" data-page="' . $total_pages . '">' . $total_pages . '</a> ';
        }

        // Next button
        if ($page < $total_pages) {
            $pagination_html .= '<a href="#" class="mxchat-page-link" data-page="' . ($page + 1) . '">' . esc_html__('Next &raquo;', 'mxchat') . '</a>';
        }

        $pagination_html .= '</div>';
    }

    wp_send_json_success(array(
        'html' => $html,
        'pagination_html' => $pagination_html,
        'total_count' => $total_records,
        'total_pages' => $total_pages,
        'page' => $page,
        'per_page' => $per_page,
        'data_source' => 'pinecone'
    ));
}

/**
 * AJAX handler for pagination - handles both WordPress DB and Pinecone data sources
 * Returns paginated entries without requiring a full page reload
 */
public function ajax_mxchat_paginate_entries() {
    check_ajax_referer('mxchat_entries_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }

    $bot_id = isset($_POST['bot_id']) ? sanitize_text_field($_POST['bot_id']) : 'default';
    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
    $search_query = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $content_type_filter = isset($_POST['content_type']) ? sanitize_key($_POST['content_type']) : '';
    $per_page = 25;

    // Check if Pinecone is enabled for this bot
    $pinecone_manager = $this->mxchat_get_pinecone_manager();
    $pinecone_options = $pinecone_manager ? $pinecone_manager->mxchat_get_bot_pinecone_options($bot_id) : array();
    $use_pinecone = ($pinecone_options['mxchat_use_pinecone'] ?? '0') === '1';
    $has_pinecone_api = !empty($pinecone_options['mxchat_pinecone_api_key']);

    if ($use_pinecone && $has_pinecone_api) {
        // Delegate to Pinecone pagination handler (pass search params)
        $_POST['page'] = $page;
        $_POST['search'] = $search_query;
        $_POST['content_type'] = $content_type_filter;
        $this->ajax_mxchat_refresh_pinecone_entries();
        return;
    }

    // WordPress DB pagination - MUST match initial page load logic exactly
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';
    $offset = ($page - 1) * $per_page;

    // Build WHERE clause for search and content type filtering
    $where_clauses = array();
    $where_values = array();

    if ($search_query) {
        $where_clauses[] = "article_content LIKE %s";
        $where_values[] = '%' . $wpdb->esc_like($search_query) . '%';
    }

    if ($content_type_filter) {
        switch ($content_type_filter) {
            case 'manual':
                $where_clauses[] = "(source_url = '' OR source_url IS NULL OR source_url LIKE 'mxchat://%')";
                break;
            case 'pdf':
                $where_clauses[] = "source_url LIKE '%.pdf'";
                break;
            case 'url':
                $where_clauses[] = "source_url != '' AND source_url IS NOT NULL AND source_url NOT LIKE 'mxchat://%' AND source_url NOT LIKE '%.pdf'";
                break;
        }
    }

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // Count grouped entries with filters applied
    if (!empty($where_values)) {
        $count_args = array_merge($where_values, $where_values);
        $count_query = $wpdb->prepare(
            "SELECT (SELECT COUNT(DISTINCT source_url) FROM {$table_name} {$where_sql} AND source_url != '' AND source_url NOT LIKE 'mxchat://%') +
                    (SELECT COUNT(*) FROM {$table_name} {$where_sql} AND (source_url = '' OR source_url IS NULL OR source_url LIKE 'mxchat://%'))",
            ...$count_args
        );
        $total_records = $wpdb->get_var($count_query);
    } else if (!empty($where_sql)) {
        // Content type filter only (no search), no prepared values needed
        $total_records = $wpdb->get_var(
            "SELECT (SELECT COUNT(DISTINCT source_url) FROM {$table_name} {$where_sql} AND source_url != '' AND source_url NOT LIKE 'mxchat://%') +
                    (SELECT COUNT(*) FROM {$table_name} {$where_sql} AND (source_url = '' OR source_url IS NULL OR source_url LIKE 'mxchat://%'))"
        );
    } else {
        // No filters
        $total_records = $wpdb->get_var(
            "SELECT (SELECT COUNT(DISTINCT source_url) FROM {$table_name} WHERE source_url != '' AND source_url NOT LIKE 'mxchat://%') +
                    (SELECT COUNT(*) FROM {$table_name} WHERE source_url = '' OR source_url IS NULL OR source_url LIKE 'mxchat://%')"
        );
    }
    $total_pages = ceil($total_records / $per_page);

    // Step 1: Get unique source_urls for this page (ordered by latest timestamp) with filters
    if (!empty($where_values)) {
        $query_args = array_merge($where_values, array($per_page, $offset));
        $urls_query = $wpdb->prepare(
            "SELECT source_url, MAX(timestamp) as latest_ts FROM {$table_name}
             {$where_sql}
             GROUP BY source_url ORDER BY latest_ts DESC LIMIT %d OFFSET %d",
            ...$query_args
        );
    } else if (!empty($where_sql)) {
        $urls_query = $wpdb->prepare(
            "SELECT source_url, MAX(timestamp) as latest_ts FROM {$table_name}
             {$where_sql}
             GROUP BY source_url ORDER BY latest_ts DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        );
    } else {
        $urls_query = $wpdb->prepare(
            "SELECT source_url, MAX(timestamp) as latest_ts FROM {$table_name}
             GROUP BY source_url ORDER BY latest_ts DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        );
    }
    $page_urls = $wpdb->get_results($urls_query);

    // Step 2: Build list of source_urls to fetch
    $url_list = array();
    $url_order_map = array();
    $order_index = 0;
    foreach ($page_urls as $url_row) {
        $url = $url_row->source_url;
        $url_list[] = $url;
        $url_order_map[$url] = $order_index++;
    }

    // Step 3: Fetch all rows for these source_urls (with search filter if applicable)
    $prompts = array();
    if (!empty($url_list)) {
        $placeholders = implode(',', array_fill(0, count($url_list), '%s'));
        if ($search_query) {
            // Include search filter in the final fetch
            $prompts_query = $wpdb->prepare(
                "SELECT id, article_content, source_url, timestamp, role_restriction
                 FROM {$table_name}
                 WHERE source_url IN ($placeholders) AND article_content LIKE %s
                 ORDER BY timestamp DESC",
                ...array_merge($url_list, ['%' . $wpdb->esc_like($search_query) . '%'])
            );
        } else {
            $prompts_query = $wpdb->prepare(
                "SELECT id, article_content, source_url, timestamp, role_restriction
                 FROM {$table_name}
                 WHERE source_url IN ($placeholders)
                 ORDER BY timestamp DESC",
                $url_list
            );
        }
        $prompts = $wpdb->get_results($prompts_query);
    }

    // Group prompts by source_url for chunk display
    $grouped_prompts = array();
    foreach ($prompts as $prompt) {
        $source_url = $prompt->source_url ?? '';

        // Parse chunk metadata using the proper chunker method (same as initial page load)
        if (class_exists('MxChat_Chunker')) {
            $chunk_meta = MxChat_Chunker::parse_stored_chunk($prompt->article_content);
            $prompt->chunk_metadata = $chunk_meta['metadata'];
            $prompt->display_content = $chunk_meta['text'];
        } else {
            $prompt->chunk_metadata = array();
            $prompt->display_content = $prompt->article_content;
        }

        if (!empty($source_url) && strpos($source_url, 'mxchat://') !== 0) {
            if (!isset($grouped_prompts[$source_url])) {
                $grouped_prompts[$source_url] = array();
            }
            $grouped_prompts[$source_url][] = $prompt;
        } else {
            // Ungrouped entries
            $grouped_prompts['_ungrouped_' . $prompt->id] = array($prompt);
        }
    }

    // Sort groups by the original URL order (newest first)
    uksort($grouped_prompts, function($a, $b) use ($url_order_map) {
        $order_a = isset($url_order_map[$a]) ? $url_order_map[$a] : PHP_INT_MAX;
        $order_b = isset($url_order_map[$b]) ? $url_order_map[$b] : PHP_INT_MAX;
        return $order_a - $order_b;
    });

    // Sort each group internally by chunk_index
    foreach ($grouped_prompts as $source_url => &$group) {
        usort($group, function($a, $b) {
            $index_a = isset($a->chunk_metadata['chunk_index']) ? intval($a->chunk_metadata['chunk_index']) : 0;
            $index_b = isset($b->chunk_metadata['chunk_index']) ? intval($b->chunk_metadata['chunk_index']) : 0;
            return $index_a - $index_b;
        });
    }
    unset($group);

    // Build HTML for the table rows
    ob_start();
    $display_index = 0;
    $current_page = $page;
    $data_source = 'wordpress';
    $current_bot_id = $bot_id;
    $preview_length = 150;

    if (empty($grouped_prompts)) {
        echo '<tr><td colspan="5" style="padding: 40px; text-align: center; color: var(--mxch-text-muted);">';
        esc_html_e('No knowledge entries found. Use the Import Options to add content.', 'mxchat');
        echo '</td></tr>';
    } else {
        foreach ($grouped_prompts as $source_url => $group) {
            $chunk_count = count($group);
            $first_prompt = $group[0];
            $display_index++;

            if ($chunk_count > 1) {
                // Multiple chunks - show grouped row with expand button
                $group_id = 'group-' . md5($source_url);
                ?>
                <tr id="prompt-<?php echo esc_attr($first_prompt->id); ?>"
                    class="mxchat-chunk-group-header"
                    data-source="<?php echo esc_attr($data_source); ?>"
                    data-group-id="<?php echo esc_attr($group_id); ?>"
                    style="border-bottom: 1px solid var(--mxch-card-border);">
                    <td style="padding: 12px 16px; text-align: center;">
                        <input type="checkbox"
                               class="mxchat-entry-checkbox"
                               data-entry-id="<?php echo esc_attr($first_prompt->id); ?>"
                               data-source="<?php echo esc_attr($data_source); ?>"
                               data-source-url="<?php echo esc_attr($source_url); ?>"
                               data-is-group="true"
                               data-chunk-count="<?php echo esc_attr($chunk_count); ?>">
                    </td>
                    <td style="padding: 12px 16px; font-size: 13px;">
                        <?php echo esc_html($first_prompt->id); ?>
                    </td>
                    <td class="mxchat-content-cell" style="padding: 12px 16px; font-size: 13px;">
                        <div class="mxchat-chunk-group-info">
                            <button type="button" class="mxchat-chunk-toggle" data-group-id="<?php echo esc_attr($group_id); ?>">
                                <span class="dashicons dashicons-arrow-right-alt2"></span>
                            </button>
                            <span class="mxchat-chunk-badge"><?php echo esc_html($chunk_count); ?> <?php esc_html_e('chunks', 'mxchat'); ?></span>
                            <span class="mxchat-chunk-preview">
                                <?php
                                $parent_content = isset($first_prompt->display_content) ? $first_prompt->display_content : $first_prompt->article_content;
                                $content_preview = mb_substr($parent_content, 0, 100);
                                echo esc_html($content_preview . '...');
                                ?>
                            </span>
                        </div>
                    </td>
                    <td class="mxchat-url-cell" style="padding: 12px 16px; font-size: 13px;">
                        <?php if (!empty($source_url) && strpos($source_url, 'mxchat://') !== 0 && strpos($source_url, '_ungrouped_') !== 0) : ?>
                            <a href="<?php echo esc_url($source_url); ?>" target="_blank" style="color: var(--mxch-primary); text-decoration: none;">
                                <span class="dashicons dashicons-external" style="font-size: 14px;"></span>
                                <?php esc_html_e('View Source', 'mxchat'); ?>
                            </a>
                        <?php else : ?>
                            <span style="color: var(--mxch-text-muted);"><?php esc_html_e('Manual Content', 'mxchat'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="mxchat-actions-cell" style="padding: 12px 16px;">
                        <button type="button"
                                class="mxch-btn mxch-btn-ghost mxch-btn-sm delete-button-group"
                                data-source-url="<?php echo esc_attr($source_url); ?>"
                                data-chunk-count="<?php echo esc_attr($chunk_count); ?>"
                                data-data-source="<?php echo esc_attr($data_source); ?>"
                                data-bot-id="<?php echo esc_attr($current_bot_id); ?>"
                                data-nonce="<?php echo wp_create_nonce('mxchat_delete_chunks_nonce'); ?>"
                                style="color: var(--mxch-error);"
                                title="<?php esc_attr_e('Delete all chunks', 'mxchat'); ?>">
                            <span class="dashicons dashicons-trash" style="font-size: 14px;"></span>
                        </button>
                    </td>
                </tr>
                <?php
                // Render hidden chunk rows
                foreach ($group as $chunk_index => $chunk) {
                    $meta_chunk_index = isset($chunk->chunk_metadata['chunk_index']) ? intval($chunk->chunk_metadata['chunk_index']) : $chunk_index;
                    $meta_total_chunks = isset($chunk->chunk_metadata['total_chunks']) ? intval($chunk->chunk_metadata['total_chunks']) : $chunk_count;
                    $content = isset($chunk->display_content) ? $chunk->display_content : $chunk->article_content;
                    $content_preview = mb_strlen($content) > $preview_length
                        ? mb_substr($content, 0, $preview_length) . '...'
                        : $content;
                    ?>
                    <tr id="prompt-<?php echo esc_attr($chunk->id); ?>"
                        class="mxchat-chunk-row <?php echo esc_attr($group_id); ?>"
                        data-source="<?php echo esc_attr($data_source); ?>"
                        style="display: none; background: #f8f9fa; border-bottom: 1px solid var(--mxch-card-border);">
                        <td style="padding: 12px 16px; text-align: center;">
                            <!-- Checkbox column placeholder for chunks (managed by group) -->
                        </td>
                        <td style="padding: 12px 16px 12px 30px; font-size: 13px;">
                            <!-- Hidden ID column for chunks -->
                        </td>
                        <td class="mxchat-content-cell" style="padding: 12px 16px; font-size: 13px;">
                            <div class="mxchat-accordion-wrapper">
                                <div class="mxchat-content-preview">
                                    <span class="mxchat-chunk-indicator" style="margin-right: 10px; color: var(--mxch-text-secondary); font-size: 12px;">
                                        <?php printf(esc_html__('Chunk %d of %d', 'mxchat'), $meta_chunk_index + 1, $meta_total_chunks); ?>
                                    </span>
                                    <span class="preview-text"><?php echo esc_html($content_preview); ?></span>
                                    <?php if (mb_strlen($content) > $preview_length) : ?>
                                        <button class="mxchat-expand-toggle" type="button">
                                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="mxchat-content-full" style="display: none;">
                                    <div class="content-view">
                                        <?php
                                        if (preg_match('/[\x{0590}-\x{05FF}]/u', $content)) {
                                            echo '<div dir="rtl" lang="he" class="rtl-content">';
                                            echo wp_kses_post(wpautop($content));
                                            echo '</div>';
                                        } else {
                                            echo wp_kses_post(wpautop($content));
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="mxchat-url-cell" style="padding: 12px 16px; font-size: 13px;">
                            <span class="mxchat-chunk-label" style="color: var(--mxch-text-muted);"><?php esc_html_e('Same as parent', 'mxchat'); ?></span>
                        </td>
                        <td class="mxchat-actions-cell" style="padding: 12px 16px;">
                            <span class="mxchat-chunk-label" style="color: var(--mxch-text-muted);"><?php esc_html_e('Managed by group', 'mxchat'); ?></span>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                // Single entry - display normally with accordion
                $prompt = $first_prompt;
                $content = isset($prompt->display_content) ? $prompt->display_content : $prompt->article_content;
                $content_preview = mb_strlen($content) > $preview_length
                    ? mb_substr($content, 0, $preview_length) . '...'
                    : $content;
                ?>
                <tr id="prompt-<?php echo esc_attr($prompt->id); ?>"
                    data-source="<?php echo esc_attr($data_source); ?>"
                    style="border-bottom: 1px solid var(--mxch-card-border);">
                    <td style="padding: 12px 16px; text-align: center;">
                        <input type="checkbox"
                               class="mxchat-entry-checkbox"
                               data-entry-id="<?php echo esc_attr($prompt->id); ?>"
                               data-source="<?php echo esc_attr($data_source); ?>"
                               data-source-url="<?php echo esc_attr($source_url); ?>"
                               data-is-group="false">
                    </td>
                    <td style="padding: 12px 16px; font-size: 13px;">
                        <?php echo esc_html($prompt->id); ?>
                    </td>
                    <td class="mxchat-content-cell" style="padding: 12px 16px; font-size: 13px;">
                        <div class="mxchat-accordion-wrapper">
                            <div class="mxchat-content-preview">
                                <span class="preview-text"><?php echo esc_html($content_preview); ?></span>
                                <?php if (mb_strlen($content) > $preview_length) : ?>
                                    <button class="mxchat-expand-toggle" type="button">
                                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="mxchat-content-full" style="display: none;">
                                <div class="content-view">
                                    <?php
                                    if (preg_match('/[\x{0590}-\x{05FF}]/u', $content)) {
                                        echo '<div dir="rtl" lang="he" class="rtl-content">';
                                        echo wp_kses_post(wpautop($content));
                                        echo '</div>';
                                    } else {
                                        echo wp_kses_post(wpautop($content));
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="mxchat-url-cell" style="padding: 12px 16px; font-size: 13px;">
                        <?php
                        $actual_source = $source_url;
                        if (strpos($source_url, '_ungrouped_') === 0) {
                            $actual_source = $prompt->source_url ?? '';
                        }
                        if (!empty($actual_source) && strpos($actual_source, 'mxchat://') !== 0) : ?>
                            <a href="<?php echo esc_url($actual_source); ?>" target="_blank" style="color: var(--mxch-primary); text-decoration: none;">
                                <span class="dashicons dashicons-external" style="font-size: 14px;"></span>
                                <?php esc_html_e('View', 'mxchat'); ?>
                            </a>
                        <?php else : ?>
                            <span style="color: var(--mxch-text-muted);"><?php esc_html_e('Manual', 'mxchat'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 12px 16px;">
                        <button type="button" class="mxch-btn mxch-btn-ghost mxch-btn-sm delete-button-wordpress" data-entry-id="<?php echo esc_attr($prompt->id); ?>" data-bot-id="<?php echo esc_attr($current_bot_id); ?>" data-nonce="<?php echo wp_create_nonce('mxchat_delete_wordpress_prompt_nonce'); ?>" style="color: var(--mxch-error);">
                            <span class="dashicons dashicons-trash" style="font-size: 14px;"></span>
                        </button>
                    </td>
                </tr>
                <?php
            }
        }
    }
    $html = ob_get_clean();

    // Generate pagination HTML (include search/filter data for subsequent pages)
    $pagination_html = '';
    if ($total_pages > 1) {
        $pagination_html = '<div class="mxchat-ajax-pagination" data-current-page="' . esc_attr($page) . '" data-total-pages="' . esc_attr($total_pages) . '" data-search="' . esc_attr($search_query) . '" data-content-type="' . esc_attr($content_type_filter) . '">';

        // Previous button
        if ($page > 1) {
            $pagination_html .= '<a href="#" class="mxchat-page-link" data-page="' . ($page - 1) . '">' . esc_html__('&laquo; Previous', 'mxchat') . '</a> ';
        }

        // Page numbers
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);

        if ($start_page > 1) {
            $pagination_html .= '<a href="#" class="mxchat-page-link" data-page="1">1</a> ';
            if ($start_page > 2) {
                $pagination_html .= '<span class="mxchat-page-dots">...</span> ';
            }
        }

        for ($i = $start_page; $i <= $end_page; $i++) {
            if ($i == $page) {
                $pagination_html .= '<span class="mxchat-page-current">' . $i . '</span> ';
            } else {
                $pagination_html .= '<a href="#" class="mxchat-page-link" data-page="' . $i . '">' . $i . '</a> ';
            }
        }

        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                $pagination_html .= '<span class="mxchat-page-dots">...</span> ';
            }
            $pagination_html .= '<a href="#" class="mxchat-page-link" data-page="' . $total_pages . '">' . $total_pages . '</a> ';
        }

        // Next button
        if ($page < $total_pages) {
            $pagination_html .= '<a href="#" class="mxchat-page-link" data-page="' . ($page + 1) . '">' . esc_html__('Next &raquo;', 'mxchat') . '</a>';
        }

        $pagination_html .= '</div>';
    }

    wp_send_json_success(array(
        'html' => $html,
        'pagination_html' => $pagination_html,
        'total_count' => $total_records,
        'total_pages' => $total_pages,
        'page' => $page,
        'per_page' => $per_page,
        'data_source' => 'wordpress'
    ));
}

/**
 * AJAX handler to detect available sitemaps on the site
 * Optimized for speed - only checks primary sitemap indexes first
 */
public function ajax_mxchat_detect_sitemaps() {
    check_ajax_referer('mxchat_detect_sitemaps_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }

    $site_url = get_site_url();
    $sitemaps = array();
    $found_index = false;

    // Only check the main sitemap index files first (much faster)
    // These are the primary entry points that contain sub-sitemaps
    $primary_indexes = array(
        'sitemap_index.xml' => 'Yoast SEO / Rank Math',  // Yoast & Rank Math
        'wp-sitemap.xml' => 'WordPress Core',            // WordPress Core
        'sitemap.xml' => 'Standard',                     // Generic/AIOSEO
    );

    foreach ($primary_indexes as $path => $source) {
        $url = trailingslashit($site_url) . $path;

        $response = wp_remote_head($url, array(
            'timeout' => 3,  // Short timeout
            'sslverify' => false,
            'redirection' => 1
        ));

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            // Found a sitemap index - parse it to get sub-sitemaps
            $sub_sitemaps = $this->parse_sitemap_index($url);
            if (!empty($sub_sitemaps)) {
                $sitemaps[] = array(
                    'url' => $url,
                    'type' => 'index',
                    'source' => $source,
                    'sub_sitemaps' => $sub_sitemaps
                );
                $found_index = true;
                // Found a valid index, no need to check others
                break;
            }
        }
    }

    // If no sitemap index found, check for standalone sitemaps
    if (!$found_index) {
        $standalone_sitemaps = array(
            'post-sitemap.xml' => array('type' => 'content', 'source' => 'Yoast SEO'),
            'page-sitemap.xml' => array('type' => 'content', 'source' => 'Yoast SEO'),
        );

        foreach ($standalone_sitemaps as $path => $info) {
            $url = trailingslashit($site_url) . $path;

            $response = wp_remote_head($url, array(
                'timeout' => 2,
                'sslverify' => false
            ));

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $sitemaps[] = array(
                    'url' => $url,
                    'type' => $info['type'],
                    'source' => $info['source'],
                    'url_count' => 0  // Skip URL count for speed
                );
            }
        }
    }

    wp_send_json_success(array(
        'sitemaps' => $sitemaps,
        'site_url' => $site_url
    ));
}

/**
 * Parse a sitemap index to get sub-sitemaps
 * Optimized: doesn't fetch URL count for each sub-sitemap (too slow)
 */
private function parse_sitemap_index($url) {
    $sub_sitemaps = array();

    $response = wp_remote_get($url, array(
        'timeout' => 5,
        'sslverify' => false
    ));

    if (is_wp_error($response)) {
        return $sub_sitemaps;
    }

    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        return $sub_sitemaps;
    }

    // Suppress XML errors
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body);
    libxml_clear_errors();

    if ($xml === false) {
        return $sub_sitemaps;
    }

    // Check if it's a sitemap index (contains <sitemap> elements)
    if (isset($xml->sitemap)) {
        foreach ($xml->sitemap as $sitemap) {
            $loc = (string) $sitemap->loc;
            if (!empty($loc)) {
                // Try to determine the type from the URL
                $type = 'content';
                if (strpos($loc, 'category') !== false || strpos($loc, 'tag') !== false || strpos($loc, 'taxonomy') !== false) {
                    $type = 'taxonomy';
                } elseif (strpos($loc, 'author') !== false || strpos($loc, 'user') !== false) {
                    $type = 'author';
                }

                // Skip URL count - too slow to fetch for each sitemap
                $sub_sitemaps[] = array(
                    'url' => $loc,
                    'type' => $type,
                    'url_count' => 0,  // Don't fetch - takes too long
                    'name' => basename(parse_url($loc, PHP_URL_PATH))
                );
            }
        }
    }

    return $sub_sitemaps;
}

/**
 * Get URL count from a sitemap
 */
private function get_sitemap_url_count($url) {
    $response = wp_remote_get($url, array(
        'timeout' => 10,
        'sslverify' => false
    ));

    if (is_wp_error($response)) {
        return 0;
    }

    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        return 0;
    }

    // Count <url> or <loc> elements
    $count = preg_match_all('/<url>/i', $body, $matches);
    return $count ?: 0;
}

/**
 * Get sitemaps declared in robots.txt
 */
private function get_sitemaps_from_robots($site_url) {
    $sitemaps = array();
    $robots_url = trailingslashit($site_url) . 'robots.txt';

    $response = wp_remote_get($robots_url, array(
        'timeout' => 5,
        'sslverify' => false
    ));

    if (is_wp_error($response)) {
        return $sitemaps;
    }

    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        return $sitemaps;
    }

    // Find Sitemap: declarations
    if (preg_match_all('/^Sitemap:\s*(.+)$/mi', $body, $matches)) {
        foreach ($matches[1] as $sitemap_url) {
            $sitemap_url = trim($sitemap_url);
            if (filter_var($sitemap_url, FILTER_VALIDATE_URL)) {
                $sitemaps[] = $sitemap_url;
            }
        }
    }

    return $sitemaps;
}

public function mxchat_stop_processing() {
    // Verify permissions
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Unauthorized access', 'mxchat'));
    }

    // Verify nonce
    check_admin_referer('mxchat_stop_processing_action', 'mxchat_stop_processing_nonce');

    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_processing_queue';
    
    // Get active queue IDs
    $sitemap_queue_id = get_transient('mxchat_active_queue_sitemap');
    $pdf_queue_id = get_transient('mxchat_active_queue_pdf');
    
    // Delete all pending items from active queues
    if ($sitemap_queue_id) {
        $wpdb->delete(
            $table_name,
            array(
                'queue_id' => $sitemap_queue_id,
                'status' => 'pending'
            ),
            array('%s', '%s')
        );
        
        delete_transient('mxchat_active_queue_sitemap');
        delete_transient('mxchat_last_sitemap_url');
    }
    
    if ($pdf_queue_id) {
        // Get PDF path before deleting
        $pdf_path = $this->mxchat_get_queue_meta($pdf_queue_id, 'pdf_path');
        
        $wpdb->delete(
            $table_name,
            array(
                'queue_id' => $pdf_queue_id,
                'status' => 'pending'
            ),
            array('%s', '%s')
        );
        
        // Delete PDF file
        if ($pdf_path && file_exists($pdf_path)) {
            wp_delete_file($pdf_path);
        }
        
        delete_transient('mxchat_active_queue_pdf');
        delete_transient('mxchat_last_pdf_url');
    }

    // Redirect back with a success message
    set_transient('mxchat_admin_notice_success',
        esc_html__('Processing has been stopped successfully.', 'mxchat'),
        30
    );
    wp_safe_redirect(admin_url('admin.php?page=mxchat-prompts'));
    exit;
}

/**
 * Get content list for processing
 */
public function ajax_mxchat_get_content_list() {
    // Verify the nonce
    check_ajax_referer('mxchat_content_selector_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized access', 'mxchat'));
    }
    
    $page = isset($_GET['page']) ? absint($_GET['page']) : 1;
    $per_page = isset($_GET['per_page']) ? absint($_GET['per_page']) : 100;
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : 'all';
    $post_status = isset($_GET['post_status']) ? sanitize_text_field($_GET['post_status']) : 'publish';
    $processed_filter = isset($_GET['processed_filter']) ? sanitize_text_field($_GET['processed_filter']) : 'all';
    
    // Build query args
    $args = array(
        'posts_per_page' => $per_page,
        'paged' => $page,
        'post_status' => $post_status !== 'all' ? $post_status : array('publish', 'draft', 'pending'),
        'orderby' => 'date',
        'order' => 'DESC',
    );
    
    // Handle post types - IMPROVED VERSION
    if ($post_type !== 'all') {
        $args['post_type'] = $post_type;
    } else {
        // Get all available post types that might contain content
        $all_post_types = array();
        
        // First get all public post types
        $public_types = get_post_types(array('public' => true), 'names');
        $all_post_types = array_merge($all_post_types, $public_types);
        
        // Add common forum/community post types
        $forum_types = array('topic', 'reply', 'forum', 'wpforo_topic', 'wpforo_post');
        foreach ($forum_types as $forum_type) {
            if (post_type_exists($forum_type)) {
                $all_post_types[] = $forum_type;
            }
        }
        
        // Add other commonly used post types
        $common_types = array('product', 'job_listing', 'event', 'portfolio');
        foreach ($common_types as $common_type) {
            if (post_type_exists($common_type)) {
                $all_post_types[] = $common_type;
            }
        }
        
        // Remove duplicates and ensure we have at least some post types
        $all_post_types = array_unique($all_post_types);
        
        if (empty($all_post_types)) {
            // Fallback to basic post types
            $all_post_types = array('post', 'page');
        }
        
        $args['post_type'] = $all_post_types;
        
        // Debug logging to see what post types are being queried
        //error_log('MxChat Debug: Querying post types: ' . implode(', ', $all_post_types));
    }
    
    if (!empty($search)) {
        $args['s'] = $search;
    }
    
    // Get processed data from storage
    $processed_data = array();
    
    $pinecone_options = get_option('mxchat_pinecone_addon_options', array());
    $use_pinecone = ($pinecone_options['mxchat_use_pinecone'] ?? '0') === '1';

    if ($use_pinecone && !empty($pinecone_options['mxchat_pinecone_api_key'])) {
        // Get fresh data from Pinecone - no caching
        $processed_data = $this->mxchat_get_pinecone_processed_content($pinecone_options);
    } else {
        // WordPress DB checking with better URL matching for all post types
        global $wpdb;
        $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';
        $processed_items = $wpdb->get_results("SELECT id, source_url, article_content, timestamp FROM {$table_name}");

        // Group items by source_url to count chunks
        $url_chunk_counts = array();
        $url_latest_timestamp = array();
        $url_first_id = array();

        if (!empty($processed_items)) {
            foreach ($processed_items as $item) {
                $url = $item->source_url;
                if (empty($url)) continue;

                // Count chunks per URL
                if (!isset($url_chunk_counts[$url])) {
                    $url_chunk_counts[$url] = 0;
                    $url_latest_timestamp[$url] = $item->timestamp;
                    $url_first_id[$url] = $item->id;
                }
                $url_chunk_counts[$url]++;

                // Track latest timestamp
                if (strtotime($item->timestamp) > strtotime($url_latest_timestamp[$url])) {
                    $url_latest_timestamp[$url] = $item->timestamp;
                }
            }

            // Now build processed_data with chunk counts
            foreach ($url_chunk_counts as $url => $chunk_count) {
                $post_id = $this->mxchat_url_to_post_id_improved($url);

                if ($post_id) {
                    $processed_data[$post_id] = array(
                        'db_id' => $url_first_id[$url],
                        'timestamp' => $url_latest_timestamp[$url],
                        'url' => $url,
                        'source' => 'wordpress',
                        'chunk_count' => $chunk_count
                    );
                }
            }
        }
    }
    
    // Get processed IDs as a simple array for in_array checks
    $processed_ids = array_keys($processed_data);
    
    // Handle processed/unprocessed filter
    if ($processed_filter === 'processed' && !empty($processed_ids)) {
        $args['post__in'] = $processed_ids;
    } elseif ($processed_filter === 'unprocessed' && !empty($processed_ids)) {
        $args['post__not_in'] = $processed_ids;
    }
    
    // Run the query
    $query = new WP_Query($args);
    $content_items = array();
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            $post_date = get_the_date();
            $excerpt = wp_trim_words(get_the_excerpt(), 20, '...');
            $word_count = str_word_count(strip_tags(get_the_content()));
            
            $is_processed = in_array($id, $processed_ids);
            $processed_date = '';
            $db_record_id = 0;
            $data_source = 'none';
            
            if ($is_processed && isset($processed_data[$id])) {
                $item_data = $processed_data[$id];
                $data_source = $item_data['source'];
                
                if ($data_source === 'wordpress' && isset($item_data['timestamp'])) {
                    // WordPress DB format
                    $timestamp = strtotime($item_data['timestamp']);
                    $processed_date = human_time_diff($timestamp, current_time('timestamp')) . ' ago';
                    $db_record_id = $item_data['db_id'];
                } elseif ($data_source === 'pinecone') {
                    // Pinecone format
                    $processed_date = $item_data['processed_date'];
                    $db_record_id = $item_data['db_id'];
                }
            }
            
            // Get chunk count for this item
            $chunk_count = 0;
            if ($is_processed && isset($processed_data[$id]['chunk_count'])) {
                $chunk_count = intval($processed_data[$id]['chunk_count']);
            }

            $content_items[] = array(
                'id' => $id,
                'title' => get_the_title(),
                'permalink' => get_permalink(),
                'date' => $post_date,
                'type' => get_post_type(),
                'status' => get_post_status(),
                'excerpt' => $excerpt,
                'word_count' => $word_count,
                'already_processed' => $is_processed,
                'processed_date' => $processed_date,
                'db_record_id' => $db_record_id,
                'data_source' => $data_source,
                'chunk_count' => $chunk_count
            );
        }
        wp_reset_postdata();
    }
    
    $response = array(
        'items' => $content_items,
        'total' => $query->found_posts,
        'total_pages' => $query->max_num_pages,
        'current_page' => $page,
        'processed_count' => count($processed_ids)
    );
    
    wp_send_json_success($response);
    exit;
}


/**
 * This function handles various WooCommerce URL formats and permalink structures
 */
private function mxchat_url_to_post_id_improved($url) {
    // First try the standard WordPress function
    $post_id = url_to_postid($url);
    
    if ($post_id > 0) {
        return $post_id;
    }
    
    // If that fails, try more aggressive URL matching
    // Remove trailing slashes and query parameters for better matching
    $clean_url = rtrim($url, '/');
    $clean_url = strtok($clean_url, '?'); // Remove query parameters
    
    // Try again with cleaned URL
    $post_id = url_to_postid($clean_url);
    if ($post_id > 0) {
        return $post_id;
    }
    
    // For bbPress forum topics, try extracting slug from URL
    if (strpos($url, '/topic/') !== false || strpos($url, '/forums/') !== false) {
        // Handle bbPress URLs: /forums/topic/topic-name/
        if (preg_match('/\/forums\/topic\/([^\/\?]+)/', $url, $matches)) {
            $topic_slug = $matches[1];
            
            // Look up topic by slug
            $topic = get_page_by_path($topic_slug, OBJECT, 'topic');
            if ($topic) {
                return $topic->ID;
            }
            
            // Alternative method: query by post_name
            global $wpdb;
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'topic' AND post_status IN ('publish', 'closed')",
                $topic_slug
            ));
            
            if ($post_id) {
                return intval($post_id);
            }
        }
        
        // Handle simpler topic URLs: /topic/topic-name/
        if (preg_match('/\/topic\/([^\/\?]+)/', $url, $matches)) {
            $topic_slug = $matches[1];
            
            global $wpdb;
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'topic' AND post_status IN ('publish', 'closed')",
                $topic_slug
            ));
            
            if ($post_id) {
                return intval($post_id);
            }
        }
    }
    
    // For WooCommerce products
    if (strpos($url, '/product/') !== false || strpos($url, 'product=') !== false) {
        // Extract product slug from various URL formats
        $product_slug = '';
        
        // Handle pretty permalinks: /product/product-name/
        if (preg_match('/\/product\/([^\/\?]+)/', $url, $matches)) {
            $product_slug = $matches[1];
        }
        // Handle query parameters: ?product=product-name
        elseif (preg_match('/[\?&]product=([^&]+)/', $url, $matches)) {
            $product_slug = $matches[1];
        }
        
        if (!empty($product_slug)) {
            // Look up product by slug
            $product = get_page_by_path($product_slug, OBJECT, 'product');
            if ($product) {
                return $product->ID;
            }
            
            // Alternative method: query by post_name
            global $wpdb;
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'product' AND post_status = 'publish'",
                $product_slug
            ));
            
            if ($post_id) {
                return intval($post_id);
            }
        }
    }
    
    // Generic approach: try to extract slug and match against all post types
    $parsed_url = wp_parse_url($clean_url);
    $path = $parsed_url['path'] ?? '';
    
    if (!empty($path)) {
        // Get the last part of the path as potential slug
        $path_parts = array_filter(explode('/', trim($path, '/')));
        $potential_slug = end($path_parts);
        
        if (!empty($potential_slug)) {
            global $wpdb;
            
            // Try to find any post with this slug
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                 WHERE post_name = %s 
                 AND post_status IN ('publish', 'closed', 'private') 
                 AND post_type NOT IN ('revision', 'attachment', 'nav_menu_item')
                 ORDER BY CASE 
                    WHEN post_type = 'post' THEN 1
                    WHEN post_type = 'page' THEN 2
                    WHEN post_type = 'topic' THEN 3
                    WHEN post_type = 'product' THEN 4
                    ELSE 5
                 END
                 LIMIT 1",
                $potential_slug
            ));
            
            if ($post_id) {
                return intval($post_id);
            }
        }
    }
    
    // ADDITIONAL: Try direct database lookup by URL variations
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';
    
    // Try variations of the URL (with/without trailing slash, http/https)
    $url_variations = array(
        $url,
        rtrim($url, '/'),
        $url . '/',
        str_replace('http://', 'https://', $url),
        str_replace('https://', 'http://', $url),
        str_replace('http://', 'https://', rtrim($url, '/')),
        str_replace('https://', 'http://', rtrim($url, '/'))
    );
    
    // Remove duplicates
    $url_variations = array_unique($url_variations);
    
    foreach ($url_variations as $variation) {
        $existing_record = $wpdb->get_row($wpdb->prepare(
            "SELECT id, source_url FROM $table_name WHERE source_url = %s",
            $variation
        ));
        
        if ($existing_record) {
            // Try to get post ID from this stored URL
            $stored_post_id = url_to_postid($existing_record->source_url);
            if ($stored_post_id > 0) {
                return $stored_post_id;
            }
        }
    }
    
    return 0; // No match found
}
/**
 * Process selected content via AJAX
 */
public function ajax_mxchat_process_selected_content() {
    // Basic request validation
    if (!check_ajax_referer('mxchat_content_selector_nonce', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        exit;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
        exit;
    }
    
    // Get post IDs - safely parse the array
    $post_ids = array();
    if (isset($_POST['post_ids']) && is_array($_POST['post_ids'])) {
        foreach ($_POST['post_ids'] as $id) {
            $post_ids[] = absint($id);
        }
    }
    
    if (empty($post_ids)) {
        wp_send_json_error('No content selected');
        exit;
    }
    
    //  Get bot_id from request
    $bot_id = isset($_POST['bot_id']) ? sanitize_key($_POST['bot_id']) : 'default';
    
    // Process only ONE post at a time to avoid request size issues
    $post_id = reset($post_ids);
    $post = get_post($post_id);
    
    if (!$post) {
        wp_send_json_error('Post not found');
        exit;
    }
    
    // Get content including title, short description (for WooCommerce), and main content
    $content = $post->post_title . "\n\n";

    // Add short description if it exists (WooCommerce products use post_excerpt for short description)
    if (!empty($post->post_excerpt)) {
        // Remove shortcode tags but preserve content inside them
        $clean_excerpt = $this->strip_shortcode_tags_preserve_content($post->post_excerpt);
        $content .= "Short Description: " . wp_strip_all_tags($clean_excerpt) . "\n\n";
    }

    // Add main content - remove shortcode tags but preserve content inside them
    $clean_content = $this->strip_shortcode_tags_preserve_content($post->post_content);
    $content .= wp_strip_all_tags($clean_content);

    // ADD WOOCOMMERCE PRODUCT DATA (pricing, stock, categories, custom tabs)
    if (get_post_type($post_id) === 'product' && class_exists('WooCommerce')) {
        $product = wc_get_product($post_id);

        if ($product) {
            // Get pricing information
            $regular_price = $product->get_regular_price();
            $sale_price = $product->get_sale_price();
            $price = $product->get_price();
            $sku = $product->get_sku();

            // Get currency symbol
            $currency_symbol = get_woocommerce_currency_symbol();

            // Add pricing information
            $content .= "\n";
            if (!empty($regular_price)) {
                $content .= "Price: " . $currency_symbol . $regular_price . "\n";
            } elseif (!empty($price)) {
                $content .= "Price: " . $currency_symbol . $price . "\n";
            }

            if (!empty($sale_price) && $sale_price !== $regular_price) {
                $content .= "Sale Price: " . $currency_symbol . $sale_price . "\n";
            }

            // Handle variable products - show price range
            if ($product->is_type('variable')) {
                $min_price = $product->get_variation_price('min');
                $max_price = $product->get_variation_price('max');
                if ($min_price !== $max_price) {
                    $content .= "Price Range: " . $currency_symbol . $min_price . " - " . $currency_symbol . $max_price . "\n";
                }
            }

            if (!empty($sku)) {
                $content .= "SKU: " . $sku . "\n";
            }

            // Get product categories
            $categories = wp_get_post_terms($post_id, 'product_cat', array('fields' => 'names'));
            if (!empty($categories) && !is_wp_error($categories)) {
                $content .= "Categories: " . implode(', ', $categories) . "\n";
            }
        }

        // Get Custom Product Tabs (supports "Custom Product Tabs for WooCommerce" by Code Parrots)
        $custom_tabs = get_post_meta($post_id, 'yikes_woo_products_tabs', true);
        if (!empty($custom_tabs) && is_array($custom_tabs)) {
            foreach ($custom_tabs as $tab) {
                $tab_title = isset($tab['title']) ? $tab['title'] : (isset($tab['tab_title']) ? $tab['tab_title'] : '');
                $tab_content = isset($tab['content']) ? $tab['content'] : '';

                if (!empty($tab_title) && !empty($tab_content)) {
                    $content .= "\n" . $tab_title . ": " . wp_strip_all_tags($tab_content) . "\n";
                }
            }
        }

        // Also check for reusable/saved tabs applied to this product
        $applied_saved_tabs = get_post_meta($post_id, 'yikes_woo_reusable_products_tabs_applied', true);
        if (!empty($applied_saved_tabs) && is_array($applied_saved_tabs)) {
            $saved_tabs = get_option('yikes_woo_reusable_products_tabs', array());
            if (!empty($saved_tabs) && is_array($saved_tabs)) {
                foreach ($applied_saved_tabs as $saved_tab_id) {
                    if (isset($saved_tabs[$saved_tab_id])) {
                        $tab = $saved_tabs[$saved_tab_id];
                        $tab_title = isset($tab['title']) ? $tab['title'] : (isset($tab['tab_title']) ? $tab['tab_title'] : '');
                        $tab_content = isset($tab['content']) ? $tab['content'] : '';

                        if (!empty($tab_title) && !empty($tab_content)) {
                            $content .= "\n" . $tab_title . ": " . wp_strip_all_tags($tab_content) . "\n";
                        }
                    }
                }
            }
        }
    }

    // ADD ACF FIELDS SUPPORT
    $acf_fields = $this->mxchat_get_acf_fields_for_post($post_id);
    if (!empty($acf_fields)) {
        $acf_content_parts = array();
        
        foreach ($acf_fields as $field_name => $field_value) {
            $formatted_value = $this->mxchat_format_acf_field_value($field_value, $field_name, $post_id);
            
            if (!empty($formatted_value)) {
                $field_label = ucwords(str_replace('_', ' ', $field_name));
                $acf_content_parts[] = $field_label . ": " . $formatted_value;
            }
        }
        
        if (!empty($acf_content_parts)) {
            $content .= "\n\n" . implode("\n", $acf_content_parts);
        }
    }

    // ADD CUSTOM POST META SUPPORT (whitelisted non-ACF meta fields)
    $custom_meta = $this->mxchat_get_whitelisted_post_meta($post_id);
    if (!empty($custom_meta)) {
        $meta_content_parts = array();

        foreach ($custom_meta as $meta_key => $meta_value) {
            // Convert meta key to readable label
            $meta_label = ucwords(str_replace(array('_', '-'), ' ', $meta_key));
            $meta_content_parts[] = $meta_label . ": " . $meta_value;
        }

        if (!empty($meta_content_parts)) {
            $content .= "\n\n" . implode("\n", $meta_content_parts);
        }
    }

    // Debug logging for WordPress Import content
    error_log('[MXCHAT-WP-IMPORT-DEBUG] Post ID: ' . $post_id . ' Title: ' . $post->post_title);
    error_log('[MXCHAT-WP-IMPORT-DEBUG] Raw post_content length: ' . strlen($post->post_content));
    error_log('[MXCHAT-WP-IMPORT-DEBUG] Final content length: ' . strlen($content));
    error_log('[MXCHAT-WP-IMPORT-DEBUG] Content preview: ' . substr($content, 0, 300));

    // Note: Removed 10,000 char limit - chunking now handles large content properly

    //  Get bot-specific API key
    $bot_options = $this->get_bot_options($bot_id);
    $options = !empty($bot_options) ? $bot_options : get_option('mxchat_options');
    $selected_model = $options['embedding_model'] ?? 'text-embedding-ada-002';
    
    if (strpos($selected_model, 'voyage') === 0) {
        $api_key = $options['voyage_api_key'] ?? '';
        $provider_name = 'Voyage AI';
    } elseif (strpos($selected_model, 'gemini-embedding') === 0) {
        $api_key = $options['gemini_api_key'] ?? '';
        $provider_name = 'Google Gemini';
    } else {
        $api_key = $options['api_key'] ?? '';
        $provider_name = 'OpenAI';
    }
    
    if (empty($api_key)) {
        MxChat_Admin::mxchat_log_debug('api_error', $provider_name . ' API key not configured for knowledge processing');
        wp_send_json_error($provider_name . ' API key not configured');
        exit;
    }
    
    $source_url = get_permalink($post_id);
    $vector_id = md5($source_url); // Vector ID for Pinecone
    
    //  Check for existing content in bot-specific storage
    $is_update = false;
    
    // Get bot-specific Pinecone configuration
    $bot_pinecone_config = $this->get_bot_pinecone_config($bot_id);
    $use_pinecone = !empty($bot_pinecone_config) && ($bot_pinecone_config['use_pinecone'] ?? false);
    
    if ($use_pinecone && !empty($bot_pinecone_config['api_key'])) {
        // Check Pinecone for this bot
        $pinecone_data = $this->mxchat_get_pinecone_processed_content($bot_pinecone_config);
        if (isset($pinecone_data[$post_id])) {
            $is_update = true;
        }
    } else {
        // Check WordPress DB (same as before since it's shared)
        global $wpdb;
        $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';
        $existing_record = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE source_url = %s",
            $source_url
        ));
        
        if ($existing_record) {
            $is_update = true;
        }
    }

    // UPDATED 2.5.6: Determine content type based on post_type
    $post_type = $post->post_type;
    $content_type = 'content'; // Default fallback

    // Map WordPress post types to content types
    switch ($post_type) {
        case 'post':
            $content_type = 'post';
            break;
        case 'page':
            $content_type = 'page';
            break;
        case 'product':
            $content_type = 'product';
            break;
        default:
            // For custom post types, use the post type name
            $content_type = sanitize_key($post_type);
            break;
    }

    //  Use the centralized utility function with bot_id and content_type
    $result = MxChat_Utils::submit_content_to_db(
        $content,
        $source_url,
        $api_key,
        $vector_id,
        $bot_id,
        $content_type
    );

    if (is_wp_error($result)) {
        MxChat_Admin::mxchat_log_debug('storage_error', 'Knowledge storage failed: ' . $result->get_error_message(), array('source_url' => $source_url));
        wp_send_json_error('Storage failed: ' . $result->get_error_message());
        exit;
    }

    //   Automatically apply role restriction based on tags
    $this->apply_role_restriction_to_post($post_id, $source_url);

    $operation_type = $is_update ? 'update' : 'new';
    
    // Count ACF fields for debugging
    $acf_field_count = count($acf_fields);
    
    // Success response with minimal data
    wp_send_json_success(array(
        'message' => $operation_type === 'update' ? 'Content updated successfully' : 'Content processed successfully',
        'post_id' => $post_id,
        'title'   => $post->post_title,
        'operation_type' => $operation_type,
        'vector_id' => $vector_id,
        'acf_fields_found' => $acf_field_count,
        'content_preview' => substr($content, 0, 100) . '...',
        'bot_id' => $bot_id
    ));
    exit;
}

private function apply_role_restriction_to_post($post_id, $source_url) {
    // Get tag-role mappings
    $mappings = get_option('mxchat_tag_role_mappings', array());
    
    if (empty($mappings)) {
        return; // No mappings, leave as public
    }
    
    // Get all tags for the post
    $post_tags = wp_get_post_tags($post_id, array('fields' => 'slugs'));
    
    if (empty($post_tags)) {
        return; // No tags, leave as public
    }
    
    // Determine the highest role restriction based on tags
    $highest_role = 'public';
    $role_hierarchy = array(
        'public' => 0,
        'logged_in' => 1,
        'subscriber' => 2,
        'contributor' => 3,
        'author' => 4,
        'editor' => 5,
        'administrator' => 6
    );
    
    foreach ($post_tags as $tag_slug) {
        if (isset($mappings[$tag_slug])) {
            $role = $mappings[$tag_slug];
            if (isset($role_hierarchy[$role]) && $role_hierarchy[$role] > $role_hierarchy[$highest_role]) {
                $highest_role = $role;
            }
        }
    }
    
    // If no restricted tags found, return (leave as public)
    if ($highest_role === 'public') {
        return;
    }
    
    // Update the role restriction in the database
    global $wpdb;
    
    // Check if using Pinecone
    $pinecone_options = get_option('mxchat_pinecone_addon_options', array());
    $use_pinecone = ($pinecone_options['mxchat_use_pinecone'] ?? '0') === '1';
    
    if ($use_pinecone && !empty($pinecone_options['mxchat_pinecone_api_key'])) {
        // Update Pinecone role restriction
        $roles_table = $wpdb->prefix . 'mxchat_pinecone_roles';
        $vector_id = md5($source_url);
        
        $wpdb->replace(
            $roles_table,
            array(
                'vector_id' => $vector_id,
                'role_restriction' => $highest_role,
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );
    } else {
        // Update WordPress DB
        $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';
        
        $wpdb->update(
            $table_name,
            array('role_restriction' => $highest_role),
            array('source_url' => $source_url),
            array('%s'),
            array('%s')
        );
    }
}

public function mxchat_get_public_post_types() {
    // Get all public post types
    $post_types = get_post_types(array('public' => true), 'objects');
    $post_type_options = array();
    
    foreach ($post_types as $post_type) {
        $post_type_options[$post_type->name] = $post_type->label;
    }
    
    // Also include common forum/community post types that might not be marked as public
    $additional_types = array(
        'topic' => 'Forum Topics (bbPress)',
        'reply' => 'Forum Replies (bbPress)', 
        'forum' => 'Forums (bbPress)',
        'wpforo_topic' => 'wpForo Topics',
        'wpforo_post' => 'wpForo Posts'
    );
    
    foreach ($additional_types as $type_name => $type_label) {
        if (post_type_exists($type_name) && !isset($post_type_options[$type_name])) {
            $post_type_options[$type_name] = $type_label;
        }
    }
    
    return $post_type_options;
}

/**
 * Retrieves processed content from Pinecone API
 */
public function mxchat_get_pinecone_processed_content($pinecone_options) {
    $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
    $host = $pinecone_options['mxchat_pinecone_host'] ?? '';

    if (empty($api_key) || empty($host)) {
        return array();
    }

    $pinecone_data = array();

    try {
        // Always get fresh data from Pinecone
        $pinecone_data = $this->mxchat_scan_pinecone_for_processed_content($pinecone_options);

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
        // Log error but return fresh data only
    }

    return $pinecone_data;
}
public function mxchat_fetch_pinecone_vectors_by_ids($pinecone_options, $vector_ids) {
    //error_log('=== DEBUG: Starting mxchat_fetch_pinecone_vectors_by_ids ===');
    
    $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
    $host = $pinecone_options['mxchat_pinecone_host'] ?? '';

    if (empty($api_key) || empty($host) || empty($vector_ids)) {
        //error_log('DEBUG: Missing parameters for fetch by IDs');
        return array();
    }

    try {
        $fetch_url = "https://{$host}/vectors/fetch";
        //error_log('DEBUG: Fetch URL: ' . $fetch_url);
        //error_log('DEBUG: Fetching ' . count($vector_ids) . ' vector IDs');

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
            //error_log('DEBUG: Fetch by IDs WP error: ' . $response->get_error_message());
            return array();
        }

        $response_code = wp_remote_retrieve_response_code($response);
        //error_log('DEBUG: Fetch response code: ' . $response_code);
        
        if ($response_code !== 200) {
            $error_body = wp_remote_retrieve_body($response);
            //error_log('DEBUG: Fetch failed with body: ' . $error_body);
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        //error_log('DEBUG: Fetch response structure: ' . print_r(array_keys($data), true));

        if (!isset($data['vectors'])) {
            //error_log('DEBUG: No vectors key in response');
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
                    $processed_date = 'Recently';

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

        //error_log('DEBUG: Processed ' . count($processed_data) . ' vectors from fetch');
        return $processed_data;

    } catch (Exception $e) {
        //error_log('DEBUG: Exception in fetch_pinecone_vectors_by_ids: ' . $e->getMessage());
        return array();
    }
}

/**
 * Scan Pinecone for processed content
 */
public function mxchat_scan_pinecone_for_processed_content($pinecone_options) {
    $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
    $host = $pinecone_options['mxchat_pinecone_host'] ?? '';

    if (empty($api_key) || empty($host)) {
        return array();
    }

    try {
        // Use multiple random vectors to get better coverage
        $all_matches = array();
        $seen_ids = array();

        // Try 3 different random vectors to get better coverage
        for ($i = 0; $i < 3; $i++) {
            $query_url = "https://{$host}/query";

            // Generate a random unit vector instead of zeros
            $random_vector = array();
            for ($j = 0; $j < 1536; $j++) {
                $random_vector[] = (rand(-1000, 1000) / 1000.0);
            }

            // Normalize the vector to unit length
            $magnitude = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $random_vector)));
            if ($magnitude > 0) {
                $random_vector = array_map(function($x) use ($magnitude) { return $x / $magnitude; }, $random_vector);
            }

            $query_data = array(
                'includeMetadata' => true,
                'includeValues' => false,
                'topK' => 10000,
                'vector' => $random_vector
            );

            $response = wp_remote_post($query_url, array(
                'headers' => array(
                    'Api-Key' => $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($query_data),
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

            if (isset($data['matches'])) {
                foreach ($data['matches'] as $match) {
                    $match_id = $match['id'] ?? '';
                    if (!empty($match_id) && !isset($seen_ids[$match_id])) {
                        $all_matches[] = $match;
                        $seen_ids[$match_id] = true;
                    }
                }
            }
        }

        // Convert matches to processed data format, grouping by URL to count chunks
        $processed_data = array();
        $url_chunk_counts = array();

        foreach ($all_matches as $match) {
            $metadata = $match['metadata'] ?? array();
            $source_url = $metadata['source_url'] ?? '';
            $match_id = $match['id'] ?? '';

            if (!empty($source_url) && !empty($match_id)) {
                $post_id = url_to_postid($source_url);
                if ($post_id) {
                    // Count chunks per post_id
                    if (!isset($url_chunk_counts[$post_id])) {
                        $url_chunk_counts[$post_id] = 0;
                    }
                    $url_chunk_counts[$post_id]++;

                    $created_at = $metadata['created_at'] ?? '';
                    $processed_date = 'Recently';

                    if (!empty($created_at)) {
                        $timestamp = is_numeric($created_at) ? $created_at : strtotime($created_at);
                        if ($timestamp) {
                            $processed_date = human_time_diff($timestamp, current_time('timestamp')) . ' ago';
                        }
                    }

                    // Only store if not already set, or update with newer timestamp
                    if (!isset($processed_data[$post_id]) ||
                        ($timestamp ?? 0) > ($processed_data[$post_id]['timestamp'] ?? 0)) {
                        $processed_data[$post_id] = array(
                            'db_id' => $match_id,
                            'processed_date' => $processed_date,
                            'url' => $source_url,
                            'source' => 'pinecone',
                            'timestamp' => $timestamp ?? current_time('timestamp')
                        );
                    }
                }
            }
        }

        // Add chunk counts to processed data
        foreach ($url_chunk_counts as $post_id => $chunk_count) {
            if (isset($processed_data[$post_id])) {
                $processed_data[$post_id]['chunk_count'] = $chunk_count;
            }
        }

        return $processed_data;

    } catch (Exception $e) {
        return array();
    }
}
/**
 *  Generate embeddings from input text for MXChat with bot support
 */
private function mxchat_generate_embedding($text, $bot_id = 'default') {
    // Enable detailed logging for debugging
    //error_log('[MXCHAT-EMBED] Starting embedding generation for bot: ' . $bot_id . '. Text length: ' . strlen($text) . ' bytes');
    //error_log('[MXCHAT-EMBED] Text preview: ' . substr($text, 0, 100) . '...');

    //  Get bot-specific options
    $bot_options = $this->get_bot_options($bot_id);
    $options = !empty($bot_options) ? $bot_options : get_option('mxchat_options');
    
    $selected_model = $options['embedding_model'] ?? 'text-embedding-ada-002';
    //error_log('[MXCHAT-EMBED] Selected embedding model for bot ' . $bot_id . ': ' . $selected_model);

    // Determine provider and endpoint
    if (strpos($selected_model, 'voyage') === 0) {
        $api_key = $options['voyage_api_key'] ?? '';
        $endpoint = 'https://api.voyageai.com/v1/embeddings';
        $provider_name = 'Voyage AI';
        //error_log('[MXCHAT-EMBED] Using Voyage AI API for bot ' . $bot_id);
    } elseif (strpos($selected_model, 'gemini-embedding') === 0) {
        $api_key = $options['gemini_api_key'] ?? '';
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $selected_model . ':embedContent';
        $provider_name = 'Google Gemini';
        //error_log('[MXCHAT-EMBED] Using Google Gemini API for bot ' . $bot_id);
    } else {
        $api_key = $options['api_key'] ?? '';
        $endpoint = 'https://api.openai.com/v1/embeddings';
        $provider_name = 'OpenAI';
        //error_log('[MXCHAT-EMBED] Using OpenAI API for bot ' . $bot_id);
    }

    //error_log('[MXCHAT-EMBED] Using endpoint: ' . $endpoint);

    if (empty($api_key)) {
        $error_message = sprintf('Missing %s API key for bot %s. Please configure your API key in the bot settings.', $provider_name, $bot_id);
        //error_log('[MXCHAT-EMBED] Error: ' . $error_message);
        return $error_message;
    }

    // Check if text is too long (for OpenAI, roughly estimate tokens as words/0.75)
    $estimated_tokens = ceil(str_word_count($text) / 0.75);
    //error_log('[MXCHAT-EMBED] Estimated token count: ~' . $estimated_tokens);

    if ($estimated_tokens > 8000 && strpos($selected_model, 'voyage') === false && strpos($selected_model, 'gemini-embedding') === false) {
        //error_log('[MXCHAT-EMBED] Warning: Text may exceed OpenAI token limits (8K for most models)');
        // Consider truncating text here
    }

    // Prepare request body based on provider
    if (strpos($selected_model, 'gemini-embedding') === 0) {
        // Gemini API format
        $request_body = array(
            'model' => 'models/' . $selected_model,
            'content' => array(
                'parts' => array(
                    array('text' => $text)
                )
            )
        );

        // Set output dimensionality to 1536 for consistency with other models
        $request_body['outputDimensionality'] = 1536;
    } else {
        // OpenAI/Voyage API format
        $request_body = array(
            'model' => $selected_model,
            'input' => $text
        );

        // Add output_dimension for voyage-3-large model
        if ($selected_model === 'voyage-3-large') {
            $request_body['output_dimension'] = 2048;
        }
    }

    //error_log('[MXCHAT-EMBED] Request prepared with model: ' . $selected_model);

    // Prepare headers based on provider
    if (strpos($selected_model, 'gemini-embedding') === 0) {
        // Gemini uses API key as query parameter
        $endpoint .= '?key=' . $api_key;
        $headers = array(
            'Content-Type' => 'application/json'
        );
    } else {
        // OpenAI/Voyage use Bearer token
        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        );
    }

    // Make API request
    //error_log('[MXCHAT-EMBED] Sending API request to: ' . $endpoint);
    $response = wp_remote_post($endpoint, array(
        'body' => wp_json_encode($request_body),
        'headers' => $headers,
        'timeout' => 60 // Increased timeout for large inputs
    ));

    // Handle wp_remote_post errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        //error_log('[MXCHAT-EMBED] WP Remote Post Error: ' . $error_message);
        return 'Connection error: ' . $error_message;
    }

    // Get and check HTTP response code
    $http_code = wp_remote_retrieve_response_code($response);
    //error_log('[MXCHAT-EMBED] API Response Code: ' . $http_code);

    if ($http_code !== 200) {
        $error_body = wp_remote_retrieve_body($response);
        //error_log('[MXCHAT-EMBED] API Error Response Body: ' . $error_body);

        // Try to parse error for more details
        $error_json = json_decode($error_body, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($error_json['error'])) {
            $error_type = $error_json['error']['type'] ?? 'unknown';
            $error_message = $error_json['error']['message'] ?? 'No message';
            //error_log('[MXCHAT-EMBED] API Error Type: ' . $error_type);
            //error_log('[MXCHAT-EMBED] API Error Message: ' . $error_message);

            // Customize error message for common API errors
            if ($error_type === 'invalid_request_error' && strpos($error_message, 'API key') !== false) {
                $error_message = sprintf('Invalid %s API key for bot %s. Please check your API key in the bot settings.', $provider_name, $bot_id);
            } elseif ($error_type === 'authentication_error') {
                $error_message = sprintf('%s authentication failed for bot %s. Please verify your API key in the bot settings.', $provider_name, $bot_id);
            }

            //error_log('[MXCHAT-EMBED] Returning error: ' . $error_message);
            return $error_message;
        }

        $error_message = sprintf("API Error (HTTP %d): Unable to generate embedding for bot %s", $http_code, $bot_id);
        //error_log('[MXCHAT-EMBED] Returning error: ' . $error_message);
        return $error_message;
    }

    // Parse response body
    $response_body = wp_remote_retrieve_body($response);
    //error_log('[MXCHAT-EMBED] Received response length: ' . strlen($response_body) . ' bytes');

    $response_data = json_decode($response_body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $error = json_last_error_msg();
        //error_log('[MXCHAT-EMBED] JSON Parse Error: ' . $error);
        //error_log('[MXCHAT-EMBED] Response preview: ' . substr($response_body, 0, 200));
        return "Failed to parse API response: $error";
    }

    // Handle different response formats based on provider
    if (strpos($selected_model, 'gemini-embedding') === 0) {
        // Gemini API response format
        if (isset($response_data['embedding']['values'])) {
            $embedding_dimensions = count($response_data['embedding']['values']);
            //error_log('[MXCHAT-EMBED] Successfully extracted Gemini embedding with ' . $embedding_dimensions . ' dimensions');

            // Check if embedding dimensions are as expected (should be 1536)
            if ($embedding_dimensions !== 1536) {
                //error_log('[MXCHAT-EMBED] Warning: Unexpected Gemini embedding dimensions: ' . $embedding_dimensions);
            }

            return $response_data['embedding']['values'];
        } else {
            //error_log('[MXCHAT-EMBED] Error: No embedding found in Gemini response');
            //error_log('[MXCHAT-EMBED] Response structure: ' . wp_json_encode(array_keys($response_data)));

            if (isset($response_data['error'])) {
                $error_message = "Gemini API Error in response: " . wp_json_encode($response_data['error']);
                //error_log('[MXCHAT-EMBED] ' . $error_message);
                return $error_message;
            }

            $error_message = "Invalid Gemini API response format: No embedding found";
            //error_log('[MXCHAT-EMBED] ' . $error_message);
            return $error_message;
        }
    } else {
        // OpenAI/Voyage API response format
        if (isset($response_data['data'][0]['embedding'])) {
            $embedding_dimensions = count($response_data['data'][0]['embedding']);
            //error_log('[MXCHAT-EMBED] Successfully extracted embedding with ' . $embedding_dimensions . ' dimensions');

            // Check if embedding dimensions are as expected
            if (($selected_model === 'text-embedding-ada-002' && $embedding_dimensions !== 1536) ||
                ($selected_model === 'voyage-3-large' && $embedding_dimensions !== 2048)) {
                //error_log('[MXCHAT-EMBED] Warning: Unexpected embedding dimensions');
            }

            return $response_data['data'][0]['embedding'];
        } else {
            //error_log('[MXCHAT-EMBED] Error: No embedding found in response');
            //error_log('[MXCHAT-EMBED] Response structure: ' . wp_json_encode(array_keys($response_data)));

            if (isset($response_data['error'])) {
                $error_message = "API Error in response: " . wp_json_encode($response_data['error']);
                //error_log('[MXCHAT-EMBED] ' . $error_message);
                return $error_message;
            }

            $error_message = "Invalid API response format: No embedding found";
            //error_log('[MXCHAT-EMBED] ' . $error_message);
            return $error_message;
        }
    }
}

/**
 * Get bot-specific options for multi-bot functionality
 * Falls back to default options if bot_id is 'default' or multi-bot add-on is not active
 */
private function get_bot_options($bot_id = 'default') {
    //error_log("MXCHAT DEBUG: get_bot_options called for bot: " . $bot_id);
    
    if ($bot_id === 'default' || !class_exists('MxChat_Multi_Bot_Manager')) {
        //error_log("MXCHAT DEBUG: Using default options (no multi-bot or bot is 'default')");
        return array();
    }
    
    $bot_options = apply_filters('mxchat_get_bot_options', array(), $bot_id);
    
    if (!empty($bot_options)) {
        //error_log("MXCHAT DEBUG: Got bot-specific options from filter");
        if (isset($bot_options['similarity_threshold'])) {
            //error_log("  - similarity_threshold: " . $bot_options['similarity_threshold']);
        }
    }
    
    return is_array($bot_options) ? $bot_options : array();
}

/**
 * Get bot-specific Pinecone configuration
 * Used in the knowledge retrieval functions
 */
// Also add debugging to your get_bot_pinecone_config function
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


public function mxchat_ajax_dismiss_completed_status() {
    try {
        // Verify the request
        check_ajax_referer('mxchat_status_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            exit;
        }
        
        $card_type = isset($_POST['card_type']) ? sanitize_text_field($_POST['card_type']) : '';
        
        if ($card_type === 'pdf') {
            // Clear PDF status
            $pdf_url = get_transient('mxchat_last_pdf_url');
            if ($pdf_url) {
                delete_transient('mxchat_pdf_status_' . md5($pdf_url));
                delete_transient('mxchat_last_pdf_url');
            }
        } elseif ($card_type === 'sitemap') {
            // Clear sitemap status
            $sitemap_url = get_transient('mxchat_last_sitemap_url');
            if ($sitemap_url) {
                delete_transient('mxchat_sitemap_status_' . md5($sitemap_url));
                delete_transient('mxchat_last_sitemap_url');
            }
        }
        
        wp_send_json_success(array('message' => 'Status dismissed successfully'));
        
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Error dismissing status: ' . $e->getMessage()));
    }
}

/**
 * Render completed status cards on page load
 * This ensures completed processing status persists through page refreshes
 */
public function mxchat_render_completed_status_cards() {
    $output = '';
    
    // Check for completed PDF status
    $pdf_url = get_transient('mxchat_last_pdf_url');
    if ($pdf_url) {
        $pdf_status = $this->mxchat_get_pdf_processing_status($pdf_url);
        if ($pdf_status && ($pdf_status['status'] === 'complete' || $pdf_status['status'] === 'error')) {
            $output .= $this->mxchat_render_pdf_status_card($pdf_status, $pdf_url);
        }
    }
    
    // Check for completed sitemap status
    $sitemap_url = get_transient('mxchat_last_sitemap_url');
    if ($sitemap_url) {
        $sitemap_status = $this->mxchat_get_sitemap_processing_status($sitemap_url);
        if ($sitemap_status && ($sitemap_status['status'] === 'complete' || $sitemap_status['status'] === 'error')) {
            $output .= $this->mxchat_render_sitemap_status_card($sitemap_status, $sitemap_url);
        }
    }
    
    return $output;
}

/**
 * Render PDF status card HTML
 */
private function mxchat_render_pdf_status_card($status, $pdf_url) {
    $html = '<div class="mxchat-status-card" data-card-type="pdf">';
    $html .= '<div class="mxchat-status-header">';
    $html .= '<h4>' . esc_html__('PDF Processing Status', 'mxchat') . '</h4>';
    
    // Add dismiss button for completed status
    if ($status['status'] === 'complete' || $status['status'] === 'error') {
        $html .= '<button type="button" class="mxchat-dismiss-button">' . esc_html__('Dismiss', 'mxchat') . '</button>';
    }
    
    // Process Batch button for processing status
    if ($status['status'] === 'processing') {
        $html .= '<button type="button" class="mxchat-manual-batch-btn" 
                  data-process-type="pdf"
                  data-url="' . esc_attr($pdf_url) . '">
                  ' . esc_html__('Process Batch', 'mxchat') . '</button>';
    }
    
    // Add status badges
    if ($status['status'] === 'error') {
        $html .= '<span class="mxchat-status-badge mxchat-status-failed">' . esc_html__('Error', 'mxchat') . '</span>';
    } elseif ($status['status'] === 'complete') {
        if ($status['failed_pages'] > 0) {
            $html .= '<span class="mxchat-status-badge mxchat-status-warning">' . 
                     sprintf(esc_html__('Completed with %d failures', 'mxchat'), $status['failed_pages']) . '</span>';
        } else {
            $html .= '<span class="mxchat-status-badge mxchat-status-success">' . esc_html__('Complete', 'mxchat') . '</span>';
        }
    }
    
    $html .= '</div>'; // End header
    
    // Progress bar
    $html .= '<div class="mxchat-progress-bar">';
    $html .= '<div class="mxchat-progress-fill" style="width: ' . esc_attr($status['percentage']) . '%"></div>';
    $html .= '</div>';
    
    // Status details
    $html .= '<div class="mxchat-status-details">';
    $html .= '<p>' . sprintf(
        esc_html__('Progress: %d of %d pages (%d%%)', 'mxchat'),
        $status['processed_pages'],
        $status['total_pages'],
        $status['percentage']
    ) . '</p>';
    
    // Show failed pages count if any
    if ($status['failed_pages'] > 0) {
        $html .= '<p><strong>' . esc_html__('Failed pages:', 'mxchat') . '</strong> ' . esc_html($status['failed_pages']) . '</p>';
    }
    
    $html .= '<p><strong>' . esc_html__('Status:', 'mxchat') . '</strong> ' . esc_html(ucfirst($status['status'])) . '</p>';
    $html .= '<p><strong>' . esc_html__('Last update:', 'mxchat') . '</strong> ' . esc_html($status['last_update']) . '</p>';
    
    // Add completion summary if available AND it's an array
    if (isset($status['completion_summary']) && is_array($status['completion_summary']) && !empty($status['completion_summary'])) {
        $summary = $status['completion_summary'];
        $html .= '<div class="mxchat-completion-summary">';
        $html .= '<h5>' . esc_html__('Processing Summary', 'mxchat') . '</h5>';
        $html .= '<p><strong>' . esc_html__('Total Pages:', 'mxchat') . '</strong> ' . esc_html($summary['total_pages']) . '</p>';
        $html .= '<p><strong>' . esc_html__('Successful:', 'mxchat') . '</strong> ' . esc_html($summary['successful_pages']) . '</p>';
        $html .= '<p><strong>' . esc_html__('Failed:', 'mxchat') . '</strong> ' . esc_html($summary['failed_pages']) . '</p>';
        $html .= '<p><strong>' . esc_html__('Completed:', 'mxchat') . '</strong> ' . esc_html($summary['completion_time']) . '</p>';
        $html .= '</div>';
    }
    
    // Add failed pages list if any AND it's an array
    if (isset($status['failed_pages_list']) && is_array($status['failed_pages_list']) && !empty($status['failed_pages_list'])) {
        $html .= $this->mxchat_render_failed_pages_list($status['failed_pages_list']);
    }
    
    // Add error message if any
    if (isset($status['error']) && !empty($status['error'])) {
        $html .= '<div class="mxchat-error-notice">';
        $html .= '<p class="error">' . esc_html($status['error']) . '</p>';
        $html .= '</div>';
    }
    
    $html .= '</div>'; // End details
    $html .= '</div>'; // End card
    
    return $html;
}
/**
 * Render sitemap status card HTML
 */
private function mxchat_render_sitemap_status_card($status, $sitemap_url) {
    $html = '<div class="mxchat-status-card" data-card-type="sitemap">';
    $html .= '<div class="mxchat-status-header">';
    $html .= '<h4>' . esc_html__('Sitemap Processing Status', 'mxchat') . '</h4>';
    
    // Add dismiss button for completed status  
    if ($status['status'] === 'complete' || $status['status'] === 'error') {
        $html .= '<button type="button" class="mxchat-dismiss-button">' . esc_html__('Dismiss', 'mxchat') . '</button>';
    }
    
    // Process Batch button for processing status
    if ($status['status'] === 'processing') {
        $html .= '<button type="button" class="mxchat-manual-batch-btn" 
                  data-process-type="sitemap"
                  data-url="' . esc_attr($sitemap_url) . '">
                  ' . esc_html__('Process Batch', 'mxchat') . '</button>';
    }

    // Add status badges
    if ($status['status'] === 'error') {
        $html .= '<span class="mxchat-status-badge mxchat-status-failed">' . esc_html__('Error', 'mxchat') . '</span>';
    } elseif ($status['status'] === 'complete') {
        if ($status['failed_urls'] > 0) {
            $html .= '<span class="mxchat-status-badge mxchat-status-warning">' . 
                     sprintf(esc_html__('Completed with %d failures', 'mxchat'), $status['failed_urls']) . '</span>';
        } else {
            $html .= '<span class="mxchat-status-badge mxchat-status-success">' . esc_html__('Complete', 'mxchat') . '</span>';
        }
    }
    
    $html .= '</div>'; // End header
    
    // Progress bar
    $html .= '<div class="mxchat-progress-bar">';
    $html .= '<div class="mxchat-progress-fill" style="width: ' . esc_attr($status['percentage']) . '%"></div>';
    $html .= '</div>';
    
    // Status details
    $html .= '<div class="mxchat-status-details">';
    $html .= '<p>' . sprintf(
        esc_html__('Progress: %d of %d URLs (%d%%)', 'mxchat'),
        $status['processed_urls'],
        $status['total_urls'],
        $status['percentage']
    ) . '</p>';
    
    // Show failed URLs count if any
    if ($status['failed_urls'] > 0) {
        $html .= '<p><strong>' . esc_html__('Failed URLs:', 'mxchat') . '</strong> ' . esc_html($status['failed_urls']) . '</p>';
    }
    
    $html .= '<p><strong>' . esc_html__('Status:', 'mxchat') . '</strong> ' . esc_html(ucfirst($status['status'])) . '</p>';
    $html .= '<p><strong>' . esc_html__('Last update:', 'mxchat') . '</strong> ' . esc_html($status['last_update']) . '</p>';
    
    // Add completion summary if available AND it's an array
    if (isset($status['completion_summary']) && is_array($status['completion_summary']) && !empty($status['completion_summary'])) {
        $summary = $status['completion_summary'];
        $html .= '<div class="mxchat-completion-summary">';
        $html .= '<h5>' . esc_html__('Processing Summary', 'mxchat') . '</h5>';
        $html .= '<p><strong>' . esc_html__('Total URLs:', 'mxchat') . '</strong> ' . esc_html($summary['total_urls']) . '</p>';
        $html .= '<p><strong>' . esc_html__('Successful:', 'mxchat') . '</strong> ' . esc_html($summary['successful_urls']) . '</p>';
        $html .= '<p><strong>' . esc_html__('Failed:', 'mxchat') . '</strong> ' . esc_html($summary['failed_urls']) . '</p>';
        $html .= '<p><strong>' . esc_html__('Completed:', 'mxchat') . '</strong> ' . esc_html($summary['completion_time']) . '</p>';
        $html .= '</div>';
    }
    
    // Add error messages if any (but not the failed URLs list)
    if (!empty($status['error']) || !empty($status['last_error'])) {
        $html .= '<div class="mxchat-error-notice">';
        
        if (!empty($status['error'])) {
            $html .= '<p class="error">' . esc_html($status['error']) . '</p>';
        }
        
        if (!empty($status['last_error'])) {
            $html .= '<p class="last-error">' . esc_html__('Last error:', 'mxchat') . ' ' . esc_html($status['last_error']) . '</p>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</div>'; // End details
    $html .= '</div>'; // End card
    
    return $html;
}


/**
 * Render failed pages list
 */
private function mxchat_render_failed_pages_list($failed_pages_list) {
    // Validate that $failed_pages_list is an array and not empty
    if (!is_array($failed_pages_list) || empty($failed_pages_list)) {
        return '';
    }
    
    $html = '<div class="mxchat-error-notice">';
    $html .= '<div class="mxchat-failed-pages-container">';
    $html .= '<h5>' . sprintf(esc_html__('Failed Pages (%d)', 'mxchat'), count($failed_pages_list)) . '</h5>';
    $html .= '<details>';
    $html .= '<summary>' . esc_html__('Show Failed Pages', 'mxchat') . '</summary>';
    $html .= '<div class="mxchat-failed-pages-list">';
    
    // Create table for failed pages
    $html .= '<table class="widefat striped">';
    $html .= '<thead><tr>';
    $html .= '<th>' . esc_html__('Page', 'mxchat') . '</th>';
    $html .= '<th>' . esc_html__('Error', 'mxchat') . '</th>';
    $html .= '<th>' . esc_html__('Retries', 'mxchat') . '</th>';
    $html .= '<th>' . esc_html__('Time', 'mxchat') . '</th>';
    $html .= '</tr></thead><tbody>';
    
    // Sort failed pages by most recent
    $sorted_failed_pages = $failed_pages_list;
    usort($sorted_failed_pages, function($a, $b) {
        return ($b['time'] ?? 0) - ($a['time'] ?? 0);
    });
    
    foreach ($sorted_failed_pages as $item) {
        // Ensure $item is an array before accessing its elements
        if (!is_array($item)) {
            continue;
        }
        
        $time_ago = isset($item['time']) ? human_time_diff($item['time'], current_time('timestamp')) . ' ' . esc_html__('ago', 'mxchat') : 'Unknown';
        $html .= '<tr>';
        $html .= '<td>' . esc_html__('Page', 'mxchat') . ' ' . esc_html($item['page'] ?? 'Unknown') . '</td>';
        $html .= '<td style="word-break: break-word;">' . esc_html($item['error'] ?? 'Unknown error') . '</td>';
        $html .= '<td>' . esc_html($item['retries'] ?? 'N/A') . '</td>';
        $html .= '<td>' . esc_html($time_ago) . '</td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
    $html .= '</div></details></div></div>';
    
    return $html;
}

/**
 * Render failed URLs list
 */
private function mxchat_render_failed_urls_list($failed_urls_list) {
    // Validate that $failed_urls_list is an array and not empty
    if (!is_array($failed_urls_list) || empty($failed_urls_list)) {
        return '';
    }
    
    $html = '<div class="mxchat-failed-urls-container">';
    $html .= '<h5>' . sprintf(esc_html__('Failed URLs (%d)', 'mxchat'), count($failed_urls_list)) . '</h5>';
    $html .= '<details>';
    $html .= '<summary>' . esc_html__('Show Failed URLs', 'mxchat') . '</summary>';
    $html .= '<div class="mxchat-failed-urls-list">';
    
    // Create table for failed URLs
    $html .= '<table class="widefat striped">';
    $html .= '<thead><tr>';
    $html .= '<th>' . esc_html__('URL', 'mxchat') . '</th>';
    $html .= '<th>' . esc_html__('Error', 'mxchat') . '</th>';
    $html .= '<th>' . esc_html__('Retries', 'mxchat') . '</th>';
    $html .= '<th>' . esc_html__('Time', 'mxchat') . '</th>';
    $html .= '</tr></thead><tbody>';
    
    // Sort failed URLs by most recent
    $sorted_failed_urls = $failed_urls_list;
    usort($sorted_failed_urls, function($a, $b) {
        return ($b['time'] ?? 0) - ($a['time'] ?? 0);
    });
    
    // Show up to 50 failed URLs
    $display_urls = array_slice($sorted_failed_urls, 0, 50);
    
    foreach ($display_urls as $item) {
        // Ensure $item is an array before accessing its elements
        if (!is_array($item)) {
            continue;
        }
        
        $url = $item['url'] ?? '';
        $time_ago = isset($item['time']) ? human_time_diff($item['time'], current_time('timestamp')) . ' ' . esc_html__('ago', 'mxchat') : 'Unknown';
        
        // Truncate URL for display
        $display_url = strlen($url) > 50 ? substr($url, 0, 47) . '...' : $url;
        
        $html .= '<tr>';
        $html .= '<td style="word-break: break-all;">';
        if (!empty($url)) {
            $html .= '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($display_url) . '</a>';
        } else {
            $html .= esc_html__('Unknown URL', 'mxchat');
        }
        $html .= '</td>';
        $html .= '<td style="word-break: break-word;">' . esc_html($item['error'] ?? 'Unknown error') . '</td>';
        $html .= '<td>' . esc_html($item['retries'] ?? 'N/A') . '</td>';
        $html .= '<td>' . esc_html($time_ago) . '</td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
    
    if (count($failed_urls_list) > 50) {
        $html .= '<div class="mxchat-failed-urls-more">+ ' . 
                 (count($failed_urls_list) - 50) . 
                 ' ' . esc_html__('more failed URLs not shown', 'mxchat') . '</div>';
    }
    
    $html .= '</div></details></div>';
    
    return $html;
}

/**
 * Get all ACF fields for a specific post, excluding any fields the user has disabled
 */
public function mxchat_get_acf_fields_for_post($post_id) {
    if (!function_exists('get_fields')) {
        return array();
    }

    $fields = get_fields($post_id);
    if (!$fields || !is_array($fields)) {
        return array();
    }

    // Get excluded fields from settings
    $excluded_fields = get_option('mxchat_acf_excluded_fields', array());
    if (!empty($excluded_fields) && is_array($excluded_fields)) {
        foreach ($excluded_fields as $excluded_field) {
            if (isset($fields[$excluded_field])) {
                unset($fields[$excluded_field]);
            }
        }
    }

    return $fields;
}

/**
 * Get all registered ACF field groups and their fields for the settings UI
 */
public function mxchat_get_all_acf_fields() {
    if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
        return array();
    }

    $all_fields = array();
    $field_groups = acf_get_field_groups();

    if (!empty($field_groups)) {
        foreach ($field_groups as $group) {
            $group_fields = acf_get_fields($group['key']);
            if (!empty($group_fields)) {
                $all_fields[$group['title']] = array();
                foreach ($group_fields as $field) {
                    $all_fields[$group['title']][] = array(
                        'name' => $field['name'],
                        'label' => $field['label'],
                        'type' => $field['type']
                    );
                }
            }
        }
    }

    return $all_fields;
}

/**
 * Get whitelisted custom post meta for a given post
 * This allows non-ACF meta fields (like OptionTree, theme meta boxes) to be included in embeddings
 */
public function mxchat_get_whitelisted_post_meta($post_id) {
    $whitelist = get_option('mxchat_custom_meta_whitelist', '');

    if (empty($whitelist)) {
        return array();
    }

    // Parse the whitelist - one meta key per line
    $meta_keys = array_filter(array_map('trim', explode("\n", $whitelist)));

    if (empty($meta_keys)) {
        return array();
    }

    $result = array();

    foreach ($meta_keys as $key) {
        // Skip empty keys
        if (empty($key)) {
            continue;
        }

        $value = get_post_meta($post_id, $key, true);

        // Only include non-empty string values
        if (!empty($value) && is_string($value)) {
            $result[$key] = $value;
        } elseif (!empty($value) && is_array($value)) {
            // Handle array values by joining them
            $flat_value = $this->mxchat_flatten_meta_array($value);
            if (!empty($flat_value)) {
                $result[$key] = $flat_value;
            }
        }
    }

    return $result;
}

/**
 * Flatten array meta values into a readable string
 */
private function mxchat_flatten_meta_array($array, $depth = 0) {
    if ($depth > 3) {
        return ''; // Prevent infinite recursion
    }

    $parts = array();

    foreach ($array as $key => $value) {
        if (is_string($value) && !empty($value)) {
            $parts[] = $value;
        } elseif (is_array($value)) {
            $nested = $this->mxchat_flatten_meta_array($value, $depth + 1);
            if (!empty($nested)) {
                $parts[] = $nested;
            }
        }
    }

    return implode(', ', $parts);
}

/**
 * Format ACF field values for content extraction
 */
public function mxchat_format_acf_field_value($value, $field_name = '', $post_id = 0) {
    if (empty($value)) {
        return '';
    }
    
    // Handle WP_Post objects first (THIS IS THE KEY FIX)
    if ($value instanceof WP_Post) {
        return $value->post_title ?: '';
    }
    
    // Handle other WP objects
    if (is_object($value)) {
        if (isset($value->post_title)) {
            return $value->post_title;
        } elseif (isset($value->display_name)) {
            return $value->display_name;
        } elseif (isset($value->name)) {
            return $value->name;
        } elseif (method_exists($value, '__toString')) {
            try {
                return (string) $value;
            } catch (Exception $e) {
                return '';
            }
        }
        // For any other objects, return empty string
        return '';
    }
    
    // Handle different ACF field types
    if (is_array($value)) {
        // Check if it's an image/file field
        if (isset($value['url'])) {
            // Image field - return alt text, title, or caption
            if (!empty($value['alt'])) {
                return $value['alt'];
            } elseif (!empty($value['title'])) {
                return $value['title'];
            } elseif (!empty($value['caption'])) {
                return $value['caption'];
            } else {
                return ''; // Don't include just the URL
            }
        }
        
        // Check if it's a post object or relationship field
        if (isset($value['post_title'])) {
            return $value['post_title'];
        }
        
        // Check if it's a user field
        if (isset($value['display_name'])) {
            return $value['display_name'];
        }
        
        // Check if it's a taxonomy term
        if (isset($value['name']) && isset($value['taxonomy'])) {
            return $value['name'];
        }
        
        // Check if it's a select field with label
        if (isset($value['label'])) {
            return $value['label'];
        }
        
        // Check for repeater field or flexible content
        if (is_numeric(key($value))) {
            $sub_values = array();
            foreach ($value as $sub_item) {
                if (is_array($sub_item)) {
                    // For repeater/flexible content, extract text values
                    $sub_text = $this->mxchat_extract_text_from_acf_array($sub_item);
                    if (!empty($sub_text)) {
                        $sub_values[] = $sub_text;
                    }
                } elseif ($sub_item instanceof WP_Post) {
                    // Handle WP_Post objects in arrays
                    $sub_values[] = $sub_item->post_title ?: '';
                } else {
                    $sub_values[] = (string) $sub_item;
                }
            }
            return implode(', ', array_filter($sub_values));
        }
        
        // For other arrays, try to extract meaningful text
        $text_values = array();
        foreach ($value as $key => $val) {
            if (is_string($val) && !empty(trim($val))) {
                $text_values[] = trim($val);
            } elseif ($val instanceof WP_Post) {
                // Handle WP_Post objects in associative arrays
                $text_values[] = $val->post_title ?: '';
            } elseif (is_array($val) && isset($val['post_title'])) {
                $text_values[] = $val['post_title'];
            } elseif (is_array($val) && isset($val['name'])) {
                $text_values[] = $val['name'];
            }
        }
        
        return implode(', ', array_filter($text_values));
    }
    
    // Handle boolean values
    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }
    
    // Handle numeric values
    if (is_numeric($value)) {
        return (string) $value;
    }
    
    // Handle string values
    if (is_string($value)) {
        return trim($value);
    }
    
    // For anything else that we can't handle, return empty string
    // This prevents the "Object could not be converted to string" error
    return '';
}

/**
 * Extract text from complex ACF array structures
 */
private function mxchat_extract_text_from_acf_array($array) {
    if (!is_array($array)) {
        return '';
    }
    
    $text_parts = array();
    
    foreach ($array as $key => $value) {
        if (is_string($value) && !empty(trim($value))) {
            // Skip keys that are likely to be IDs or technical values
            if (!is_numeric($value) || strlen($value) > 10) {
                $text_parts[] = trim($value);
            }
        } elseif ($value instanceof WP_Post) {
            // Handle WP_Post objects
            $text_parts[] = $value->post_title ?: '';
        } elseif (is_array($value)) {
            if (isset($value['post_title'])) {
                $text_parts[] = $value['post_title'];
            } elseif (isset($value['name'])) {
                $text_parts[] = $value['name'];
            } elseif (isset($value['label'])) {
                $text_parts[] = $value['label'];
            }
        } elseif (is_object($value)) {
            // Handle other objects safely
            if (isset($value->post_title)) {
                $text_parts[] = $value->post_title;
            } elseif (isset($value->name)) {
                $text_parts[] = $value->name;
            } elseif (isset($value->display_name)) {
                $text_parts[] = $value->display_name;
            }
        }
    }
    
    return implode(', ', array_filter($text_parts));
}

/**
 * Handle ACF save - fires after ACF fields are saved
 * This ensures ACF field data is available when syncing to knowledge base
 */
public function mxchat_handle_acf_save($post_id) {
    // Skip if not a valid post
    if (!$post_id || $post_id === 'options') {
        return;
    }

    // Skip autosaves and revisions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || wp_is_post_revision($post_id)) {
        return;
    }

    $post = get_post($post_id);
    if (!$post) {
        return;
    }

    $post_type = $post->post_type;

    // Check if sync is enabled for this post type
    $should_sync = false;

    if ($post_type === 'post' && get_option('mxchat_auto_sync_posts') === '1') {
        $should_sync = true;
    } else if ($post_type === 'page' && get_option('mxchat_auto_sync_pages') === '1') {
        $should_sync = true;
    } else if ($post_type === 'product' && class_exists('WooCommerce')) {
        // WooCommerce products - check if WooCommerce integration is enabled
        $options = get_option('mxchat_options', array());
        if (isset($options['enable_woocommerce_integration']) &&
            ($options['enable_woocommerce_integration'] === '1' || $options['enable_woocommerce_integration'] === 'on')) {
            $should_sync = true;
        }
    } else {
        // Check custom post types
        $option_name = 'mxchat_auto_sync_' . $post_type;
        if (get_option($option_name) === '1') {
            $should_sync = true;
        }
    }

    if (!$should_sync) {
        return;
    }

    // Only process published posts
    if ($post->post_status !== 'publish') {
        return;
    }

    // Check if this post has any ACF fields - if not, no need to re-sync
    $acf_fields = $this->mxchat_get_acf_fields_for_post($post_id);
    if (empty($acf_fields)) {
        return;
    }

    // Use a transient to prevent duplicate processing (post_updated may have already run)
    $transient_key = 'mxchat_acf_synced_' . $post_id;
    if (get_transient($transient_key)) {
        return;
    }
    set_transient($transient_key, true, 60); // Prevent re-processing for 60 seconds

    // Re-run the sync with ACF data now available
    // We pass $update=true since this is effectively an update with ACF data
    $this->mxchat_handle_post_update($post_id, $post, true);
}

public function mxchat_handle_post_update($post_id, $post, $update) {
    // Basic validation checks
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || wp_is_post_revision($post_id)) {
        return;
    }
    
    $post_type = $post->post_type;
    
    // Check if sync is enabled for this post type
    $should_sync = false;
    
    // Check built-in post types first
    if ($post_type === 'post' && get_option('mxchat_auto_sync_posts') === '1') {
        $should_sync = true;
    } else if ($post_type === 'page' && get_option('mxchat_auto_sync_pages') === '1') {
        $should_sync = true;
    } else {
        // Check custom post types
        $option_name = 'mxchat_auto_sync_' . $post_type;
        if (get_option($option_name) === '1') {
            $should_sync = true;
        }
    }
    
    if (!$should_sync) {
        return;
    }
    
    // Check if we have stored the previous status and URL in our transients
    $previous_status_key = 'mxchat_prev_status_' . $post_id;
    $previous_status = get_transient($previous_status_key);
    
    $previous_url_key = 'mxchat_prev_url_' . $post_id;
    $previous_url = get_transient($previous_url_key);
    
    // If the post was previously published but is now not published, remove from knowledge base
    if ($previous_status === 'publish' && $post->post_status !== 'publish') {
        // Use the stored URL from when it was published, or fall back to current permalink
        $source_url = $previous_url ?: get_permalink($post_id);
        
        if ($source_url) {
            // Check if Pinecone is enabled
            $pinecone_options = get_option('mxchat_pinecone_addon_options', array());
            $use_pinecone = ($pinecone_options['mxchat_use_pinecone'] ?? '0') === '1';

            if ($use_pinecone && !empty($pinecone_options['mxchat_pinecone_api_key'])) {
                // Delete from Pinecone
                $this->mxchat_delete_from_pinecone_by_url($source_url, $pinecone_options);
            } else {
                // Delete from WordPress DB
                global $wpdb;
                $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';
                
                $result = $wpdb->delete(
                    $table_name,
                    array('source_url' => $source_url),
                    array('%s')
                );
            }
        }
        
        // Clean up the transients and exit early
        delete_transient($previous_status_key);
        delete_transient($previous_url_key);
        return;
    }
    
    // Store the current status for next time (if this is an update)
    if ($update) {
        set_transient($previous_status_key, $post->post_status, DAY_IN_SECONDS);
        
        // If the post is currently published, also store its URL
        if ($post->post_status === 'publish') {
            $current_url = get_permalink($post_id);
            set_transient($previous_url_key, $current_url, DAY_IN_SECONDS);
        }
    }
    
    // Only process currently published content for adding/updating
    if ($post->post_status === 'publish') {
        // Get the source URL
        $source_url = get_permalink($post_id);
        
        // Get content with proper formatting (matching ajax_mxchat_process_selected_content)
        $title = get_the_title($post_id);
        $content = get_post_field('post_content', $post_id);
        $excerpt = get_post_field('post_excerpt', $post_id);

        // Remove shortcode tags but preserve content inside them
        $content = $this->strip_shortcode_tags_preserve_content($content);
        $excerpt = $this->strip_shortcode_tags_preserve_content($excerpt);

        // Strip tags but preserve structure (don't use 'the_content' filter as it may re-add shortcodes)
        $content = wp_strip_all_tags($content);

        // Combine title, short description (if exists), and content
        $final_content = $title . "\n\n";

        // Add short description if it exists (WooCommerce products use post_excerpt for short description)
        if (!empty($excerpt)) {
            $final_content .= "Short Description: " . wp_strip_all_tags($excerpt) . "\n\n";
        }

        $final_content .= $content;

        // For WooCommerce products, include pricing and product details
        if ($post_type === 'product' && class_exists('WooCommerce')) {
            $product = wc_get_product($post_id);

            if ($product) {
                // Get pricing information
                $regular_price = $product->get_regular_price();
                $sale_price = $product->get_sale_price();
                $price = $product->get_price();
                $sku = $product->get_sku();

                // Get currency symbol
                $currency_symbol = get_woocommerce_currency_symbol();

                // Add pricing information
                $final_content .= "\n";
                if (!empty($regular_price)) {
                    $final_content .= "Price: " . $currency_symbol . $regular_price . "\n";
                } elseif (!empty($price)) {
                    $final_content .= "Price: " . $currency_symbol . $price . "\n";
                }

                if (!empty($sale_price) && $sale_price !== $regular_price) {
                    $final_content .= "Sale Price: " . $currency_symbol . $sale_price . "\n";
                }

                // Handle variable products - show price range
                if ($product->is_type('variable')) {
                    $min_price = $product->get_variation_price('min');
                    $max_price = $product->get_variation_price('max');
                    if ($min_price !== $max_price) {
                        $final_content .= "Price Range: " . $currency_symbol . $min_price . " - " . $currency_symbol . $max_price . "\n";
                    }
                }

                if (!empty($sku)) {
                    $final_content .= "SKU: " . $sku . "\n";
                }

                // Get product categories
                $categories = wp_get_post_terms($post_id, 'product_cat', array('fields' => 'names'));
                if (!empty($categories) && !is_wp_error($categories)) {
                    $final_content .= "Categories: " . implode(', ', $categories) . "\n";
                }
            }
        }

        // For custom post types like job_listing, include additional fields
        if ($post_type === 'job_listing') {
            // Add job-specific meta if available
            $job_location = get_post_meta($post_id, '_job_location', true);
            if (!empty($job_location)) {
                $final_content .= "\n\nLocation: " . $job_location;
            }

            // Get job type terms
            $job_types = get_the_terms($post_id, 'job_listing_type');
            if (!empty($job_types) && !is_wp_error($job_types)) {
                $types = array();
                foreach ($job_types as $type) {
                    $types[] = $type->name;
                }
                $final_content .= "\n\nJob Type: " . implode(', ', $types);
            }

            // Get company name if available
            $company_name = get_post_meta($post_id, '_company_name', true);
            if (!empty($company_name)) {
                $final_content .= "\n\nCompany: " . $company_name;
            }
        }

        // ADD ACF FIELDS SUPPORT (matches ajax_mxchat_process_selected_content behavior)
        $acf_fields = $this->mxchat_get_acf_fields_for_post($post_id);
        if (!empty($acf_fields)) {
            $acf_content_parts = array();

            foreach ($acf_fields as $field_name => $field_value) {
                $formatted_value = $this->mxchat_format_acf_field_value($field_value, $field_name, $post_id);
                if (!empty($formatted_value)) {
                    // Convert field name to readable label
                    $field_label = ucwords(str_replace(['_', '-'], ' ', $field_name));
                    $acf_content_parts[] = $field_label . ": " . $formatted_value;
                }
            }

            if (!empty($acf_content_parts)) {
                $final_content .= "\n\n" . implode("\n", $acf_content_parts);
            }
        }

        // ADD CUSTOM POST META SUPPORT (whitelisted non-ACF meta fields)
        $custom_meta = $this->mxchat_get_whitelisted_post_meta($post_id);
        if (!empty($custom_meta)) {
            $meta_content_parts = array();

            foreach ($custom_meta as $meta_key => $meta_value) {
                // Convert meta key to readable label
                $meta_label = ucwords(str_replace(array('_', '-'), ' ', $meta_key));
                $meta_content_parts[] = $meta_label . ": " . $meta_value;
            }

            if (!empty($meta_content_parts)) {
                $final_content .= "\n\n" . implode("\n", $meta_content_parts);
            }
        }

        // Get API key with proper model detection
        $options = get_option('mxchat_options');
        $selected_model = $options['embedding_model'] ?? 'text-embedding-ada-002';
        
        if (strpos($selected_model, 'voyage') === 0) {
            $api_key = $options['voyage_api_key'] ?? '';
        } elseif (strpos($selected_model, 'gemini-embedding') === 0) {
            $api_key = $options['gemini_api_key'] ?? '';
        } else {
            $api_key = $options['api_key'] ?? '';
        }
        
        if (empty($api_key)) {
            return;
        }
        
        // Use the centralized utility function for storage
        $result = MxChat_Utils::submit_content_to_db(
            $final_content, 
            $source_url, 
            $api_key,
            md5($source_url) // Vector ID for Pinecone
        );
        
        //   After successful storage, apply role restriction based on tags
        if (!is_wp_error($result)) {
            $this->apply_role_restriction_to_post($post_id, $source_url);
        }
    }
    
    // Clean up the stored previous status if not used above
    if ($previous_status !== 'publish' || $post->post_status === 'publish') {
        delete_transient($previous_status_key);
        delete_transient($previous_url_key);
    }
}

/**
 * Store the post status and URL before update to detect status transitions
 * This runs before the post is actually updated in the database
 */
public function mxchat_store_pre_update_status($post_id, $data) {
    // Get the current post from database (before update)
    $current_post = get_post($post_id);
    
    if ($current_post) {
        // Store the current status temporarily
        $status_key = 'mxchat_prev_status_' . $post_id;
        set_transient($status_key, $current_post->post_status, HOUR_IN_SECONDS);
        
        // If the post is currently published, also store its URL
        if ($current_post->post_status === 'publish') {
            $url_key = 'mxchat_prev_url_' . $post_id;
            $current_url = get_permalink($post_id);
            set_transient($url_key, $current_url, HOUR_IN_SECONDS);
        }
    }
}

public function mxchat_handle_post_delete($post_id) {
    // Get post data before it's deleted
    $post = get_post($post_id);

    // Basic validation
    if (!$post || wp_is_post_revision($post_id)) {
        return;
    }

    $post_type = $post->post_type;
    
    // Check if sync is enabled for this post type
    $should_sync = false;
    
    // Check built-in post types first
    if ($post_type === 'post' && get_option('mxchat_auto_sync_posts') === '1') {
        $should_sync = true;
    } else if ($post_type === 'page' && get_option('mxchat_auto_sync_pages') === '1') {
        $should_sync = true;
    } else {
        // Check custom post types
        $option_name = 'mxchat_auto_sync_' . $post_type;
        if (get_option($option_name) === '1') {
            $should_sync = true;
        }
    }
    
    if (!$should_sync) {
        return;
    }

    // Get the URL before post is deleted
    $source_url = get_permalink($post_id);
    if (!$source_url) {
        //error_log('MXChat: Failed to get permalink for post ' . $post_id);
        return;
    }

    // Use chunk-aware deletion (handles both chunked and non-chunked content)
    $delete_result = MxChat_Utils::delete_chunks_for_url($source_url, 'default');

    if (is_wp_error($delete_result)) {
        //error_log('MXChat: Chunk-aware deletion failed for URL: ' . $source_url . ' - ' . $delete_result->get_error_message());
    }
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



public function mxchat_handle_product_change($post_id, $post, $update) {
    if ($post->post_type !== 'product') {
        return;
    }

    if ($post->post_status === 'publish') {
        add_action('shutdown', function() use ($post_id) {
            $product = wc_get_product($post_id);
            if ($product) {
                $this->mxchat_store_product_embedding($product);
            }
        });
    }
}

/**
 * Store WooCommerce product embeddings
 */
private function mxchat_store_product_embedding($product) {
    if (!isset($this->options['enable_woocommerce_integration']) ||
        !in_array($this->options['enable_woocommerce_integration'], ['1', 'on'])) {
        return;
    }

    $source_url = get_permalink($product->get_id());
    $product_id = $product->get_id();

    // Build product content
    $title = $product->get_name();
    $description = $product->get_description();
    $short_description = $product->get_short_description();
    $regular_price = $product->get_regular_price();
    $sale_price = $product->get_sale_price();
    $price = $product->get_price();
    $sku = $product->get_sku();

    // Get currency symbol
    $currency_symbol = get_woocommerce_currency_symbol();

    // Format content consistently
    $content = $title . "\n\n";

    if (!empty($short_description)) {
        $content .= "Short Description: " . wp_strip_all_tags($short_description) . "\n\n";
    }

    if (!empty($description)) {
        $content .= wp_strip_all_tags($description) . "\n\n";
    }

    // Add pricing information
    if (!empty($regular_price)) {
        $content .= "Price: " . $currency_symbol . $regular_price . "\n";
    } elseif (!empty($price)) {
        $content .= "Price: " . $currency_symbol . $price . "\n";
    }

    if (!empty($sale_price) && $sale_price !== $regular_price) {
        $content .= "Sale Price: " . $currency_symbol . $sale_price . "\n";
    }

    // Handle variable products - show price range
    if ($product->is_type('variable')) {
        $min_price = $product->get_variation_price('min');
        $max_price = $product->get_variation_price('max');
        if ($min_price !== $max_price) {
            $content .= "Price Range: " . $currency_symbol . $min_price . " - " . $currency_symbol . $max_price . "\n";
        }
    }

    if (!empty($sku)) {
        $content .= "SKU: " . $sku . "\n";
    }

    // Get product categories
    $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'));
    if (!empty($categories) && !is_wp_error($categories)) {
        $content .= "Categories: " . implode(', ', $categories) . "\n";
    }

    // Get Custom Product Tabs (supports "Custom Product Tabs for WooCommerce" by Code Parrots)
    $custom_tabs = get_post_meta($product_id, 'yikes_woo_products_tabs', true);
    if (!empty($custom_tabs) && is_array($custom_tabs)) {
        foreach ($custom_tabs as $tab) {
            $tab_title = isset($tab['title']) ? $tab['title'] : (isset($tab['tab_title']) ? $tab['tab_title'] : '');
            $tab_content = isset($tab['content']) ? $tab['content'] : '';

            if (!empty($tab_title) && !empty($tab_content)) {
                $content .= "\n" . $tab_title . ": " . wp_strip_all_tags($tab_content) . "\n";
            }
        }
    }

    // Also check for reusable/saved tabs applied to this product
    $applied_saved_tabs = get_post_meta($product_id, 'yikes_woo_reusable_products_tabs_applied', true);
    if (!empty($applied_saved_tabs) && is_array($applied_saved_tabs)) {
        // Get the saved tabs option
        $saved_tabs = get_option('yikes_woo_reusable_products_tabs', array());
        if (!empty($saved_tabs) && is_array($saved_tabs)) {
            foreach ($applied_saved_tabs as $saved_tab_id) {
                if (isset($saved_tabs[$saved_tab_id])) {
                    $tab = $saved_tabs[$saved_tab_id];
                    $tab_title = isset($tab['title']) ? $tab['title'] : (isset($tab['tab_title']) ? $tab['tab_title'] : '');
                    $tab_content = isset($tab['content']) ? $tab['content'] : '';

                    if (!empty($tab_title) && !empty($tab_content)) {
                        $content .= "\n" . $tab_title . ": " . wp_strip_all_tags($tab_content) . "\n";
                    }
                }
            }
        }
    }

    // Get API key with proper model detection
    $options = get_option('mxchat_options');
    $selected_model = $options['embedding_model'] ?? 'text-embedding-ada-002';

    if (strpos($selected_model, 'voyage') === 0) {
        $api_key = $options['voyage_api_key'] ?? '';
    } elseif (strpos($selected_model, 'gemini-embedding') === 0) {
        $api_key = $options['gemini_api_key'] ?? '';
    } else {
        $api_key = $options['api_key'] ?? '';
    }

    if (empty($api_key)) {
        //error_log('MxChat Auto-sync: No API key configured for embedding model');
        return;
    }

    // Use the centralized utility function for storage
    $result = MxChat_Utils::submit_content_to_db(
        $content,
        $source_url,
        $api_key,
        md5($source_url) // Vector ID for Pinecone
    );

    //   After successful storage, apply role restriction based on tags
    if (!is_wp_error($result)) {
        $this->apply_role_restriction_to_post($product_id, $source_url);
    }

    if (is_wp_error($result)) {
        //error_log('MxChat WooCommerce sync failed for product ' . $product_id . ': ' . $result->get_error_message());
    }
}

public function mxchat_handle_product_delete($post_id) {
    if (get_post_type($post_id) !== 'product') {
        return;
    }

    $source_url = get_permalink($post_id);

    // Check if Pinecone is enabled
    $pinecone_options = get_option('mxchat_pinecone_addon_options', array());
    $use_pinecone = ($pinecone_options['mxchat_use_pinecone'] ?? '0') === '1';

    if ($use_pinecone && !empty($pinecone_options['mxchat_pinecone_api_key'])) {
        // Delete from Pinecone
        $this->mxchat_delete_from_pinecone_by_url($source_url, $pinecone_options);
    } else {
        // Delete from WordPress DB
        global $wpdb;
        $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';
        
        $wpdb->delete(
            $table_name,
            array('source_url' => $source_url),
            array('%s')
        );
    }
}

/**
 * Handle individual Pinecone content deletion
 */
public function mxchat_handle_pinecone_prompt_delete() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions.', 'mxchat'));
    }
    
    // Verify nonce
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'mxchat_delete_pinecone_prompt_nonce')) {
        wp_die(esc_html__('Security check failed.', 'mxchat'));
    }
    
    $vector_id = isset($_GET['vector_id']) ? sanitize_text_field($_GET['vector_id']) : '';
    
    if (empty($vector_id)) {
        set_transient('mxchat_admin_notice_error', 
            esc_html__('Invalid vector ID.', 'mxchat'), 
            30
        );
        wp_safe_redirect(admin_url('admin.php?page=mxchat-prompts'));
        exit;
    }
    
    // Get Pinecone settings
    $pinecone_options = get_option('mxchat_pinecone_addon_options', array());
    $use_pinecone = ($pinecone_options['mxchat_use_pinecone'] ?? '0') === '1';
    
    if (!$use_pinecone || empty($pinecone_options['mxchat_pinecone_api_key'])) {
        set_transient('mxchat_admin_notice_error', 
            esc_html__('Pinecone is not properly configured.', 'mxchat'), 
            30
        );
        wp_safe_redirect(admin_url('admin.php?page=mxchat-prompts'));
        exit;
    }
    
    // Delete from Pinecone
    $pinecone_manager = MxChat_Pinecone_Manager::get_instance();
    $result = $pinecone_manager->mxchat_delete_from_pinecone_by_vector_id(
        $vector_id, 
        $pinecone_options['mxchat_pinecone_api_key'], 
        $pinecone_options['mxchat_pinecone_host']
    );
    
    if ($result['success']) {
        // No cache clearing needed since we removed caching
        set_transient('mxchat_admin_notice_success', 
            esc_html__('Entry deleted successfully from Pinecone.', 'mxchat'), 
            30
        );
    } else {
        set_transient('mxchat_admin_notice_error', 
            esc_html__('Failed to delete entry: ', 'mxchat') . $result['message'], 
            30
        );
    }
    
    wp_safe_redirect(admin_url('admin.php?page=mxchat-prompts'));
    exit;
}
/**
 * Handle individual Pinecone content deletion via AJAX
 */
public function ajax_mxchat_delete_pinecone_prompt() {
    // Verify nonce and permissions
    if (!check_ajax_referer('mxchat_delete_pinecone_prompt_nonce', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        exit;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
        exit;
    }
    
    $vector_id = isset($_POST['vector_id']) ? sanitize_text_field($_POST['vector_id']) : '';
    $bot_id = isset($_POST['bot_id']) ? sanitize_text_field($_POST['bot_id']) : 'default';
    
    if (empty($vector_id)) {
        wp_send_json_error('Missing vector ID');
        exit;
    }
    
    // Get bot-specific Pinecone settings
    $pinecone_manager = MxChat_Pinecone_Manager::get_instance();
    $pinecone_options = $pinecone_manager->mxchat_get_bot_pinecone_options($bot_id);
    
    $use_pinecone = ($pinecone_options['mxchat_use_pinecone'] ?? '0') === '1';
    
    if (!$use_pinecone || empty($pinecone_options['mxchat_pinecone_api_key'])) {
        wp_send_json_error('Pinecone is not properly configured for bot: ' . $bot_id);
        exit;
    }
    
    // Delete from the correct Pinecone index
    $result = $pinecone_manager->mxchat_delete_from_pinecone_by_vector_id(
        $vector_id, 
        $pinecone_options['mxchat_pinecone_api_key'], 
        $pinecone_options['mxchat_pinecone_host']
    );
    
    if ($result['success']) {
        // No cache clearing needed since we removed caching
        wp_send_json_success(array(
            'message' => 'Entry deleted successfully from Pinecone',
            'vector_id' => $vector_id,
            'bot_id' => $bot_id
        ));
    } else {
        MxChat_Admin::mxchat_log_debug('pinecone_error', 'Failed to delete from Pinecone: ' . $result['message'], array('vector_id' => $vector_id, 'bot_id' => $bot_id));
        wp_send_json_error('Failed to delete from Pinecone: ' . $result['message']);
    }
    
    exit;
}

/**
 * Handle deletion of all chunks for a given source URL via AJAX
 * Follows the same pattern as ajax_mxchat_delete_pinecone_prompt
 */
public function ajax_mxchat_delete_chunks_by_url() {
    // Verify nonce and permissions
    if (!check_ajax_referer('mxchat_delete_chunks_nonce', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        exit;
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
        exit;
    }

    $source_url = isset($_POST['source_url']) ? esc_url_raw($_POST['source_url']) : '';
    $data_source = isset($_POST['data_source']) ? sanitize_text_field($_POST['data_source']) : 'wordpress';
    $bot_id = isset($_POST['bot_id']) ? sanitize_text_field($_POST['bot_id']) : 'default';

    if (empty($source_url)) {
        wp_send_json_error('Missing source URL');
        exit;
    }

    // Generate the base vector ID from the source URL (same as how chunks are created)
    $base_vector_id = md5($source_url);

    if ($data_source === 'pinecone') {
        // Get bot-specific Pinecone settings (same as working delete function)
        $pinecone_manager = MxChat_Pinecone_Manager::get_instance();
        $pinecone_options = $pinecone_manager->mxchat_get_bot_pinecone_options($bot_id);

        $use_pinecone = ($pinecone_options['mxchat_use_pinecone'] ?? '0') === '1';

        if (!$use_pinecone || empty($pinecone_options['mxchat_pinecone_api_key'])) {
            wp_send_json_error('Pinecone is not properly configured for bot: ' . $bot_id);
            exit;
        }

        $api_key = $pinecone_options['mxchat_pinecone_api_key'];
        $host = $pinecone_options['mxchat_pinecone_host'];
        $namespace = $pinecone_options['mxchat_pinecone_namespace'] ?? '';

        // Collect all vector IDs to delete
        $vectors_to_delete = array();

        // Add the original single-vector ID (for non-chunked content)
        $vectors_to_delete[] = $base_vector_id;

        // Use Pinecone list API to find all chunk vectors with this prefix
        // NOTE: Pinecone List API is a GET request with query parameters, not POST
        $prefix = $base_vector_id . '_chunk_';

        $query_params = array(
            'prefix' => $prefix,
            'limit' => 100
        );

        if (!empty($namespace)) {
            $query_params['namespace'] = $namespace;
        }

        $list_url = "https://{$host}/vectors/list?" . http_build_query($query_params);

        $list_response = wp_remote_get($list_url, array(
            'headers' => array(
                'Api-Key' => $api_key,
                'accept' => 'application/json'
            ),
            'timeout' => 30
        ));

        if (!is_wp_error($list_response)) {
            $list_body_response = wp_remote_retrieve_body($list_response);
            $list_data = json_decode($list_body_response, true);
            if (!empty($list_data['vectors'])) {
                foreach ($list_data['vectors'] as $vector) {
                    if (isset($vector['id'])) {
                        $vectors_to_delete[] = $vector['id'];
                    }
                }
            }
        }

        if (empty($vectors_to_delete)) {
            wp_send_json_success(array(
                'message' => 'No vectors found to delete',
                'source_url' => $source_url
            ));
            exit;
        }

        // Delete all vectors using the same endpoint as the working function
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
            MxChat_Admin::mxchat_log_debug('pinecone_error', 'Failed to delete chunks from Pinecone: ' . $delete_response->get_error_message(), array('source_url' => $source_url));
            wp_send_json_error('Failed to delete from Pinecone: ' . $delete_response->get_error_message());
            exit;
        }

        $response_code = wp_remote_retrieve_response_code($delete_response);

        if ($response_code !== 200) {
            MxChat_Admin::mxchat_log_debug('pinecone_error', 'Pinecone API error (HTTP ' . $response_code . ')', array('source_url' => $source_url));
            wp_send_json_error('Pinecone API error (HTTP ' . $response_code . ')');
            exit;
        }

        wp_send_json_success(array(
            'message' => 'All chunks deleted successfully from Pinecone',
            'source_url' => $source_url,
            'deleted_count' => count($vectors_to_delete)
        ));

    } else {
        // WordPress database deletion
        global $wpdb;
        $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';

        $result = $wpdb->delete(
            $table_name,
            array('source_url' => $source_url),
            array('%s')
        );

        if ($result === false) {
            MxChat_Admin::mxchat_log_debug('database_error', 'Failed to delete from database: ' . $wpdb->last_error, array('source_url' => $source_url));
            wp_send_json_error('Failed to delete from database: ' . $wpdb->last_error);
            exit;
        }

        wp_send_json_success(array(
            'message' => 'All chunks deleted successfully from database',
            'source_url' => $source_url,
            'deleted_count' => $result
        ));
    }

    exit;
}

/**
 * Handle individual WordPress database content deletion via AJAX
 * Mirrors the Pinecone delete handler but for WordPress database entries
 */
public function ajax_mxchat_delete_wordpress_prompt() {
    // Verify nonce and permissions
    if (!check_ajax_referer('mxchat_delete_wordpress_prompt_nonce', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        exit;
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
        exit;
    }

    $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;

    if (empty($entry_id)) {
        wp_send_json_error('Missing entry ID');
        exit;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';

    // Clear cache for this entry
    wp_cache_delete('prompt_' . $entry_id, 'mxchat_prompts');

    // Delete from database
    $result = $wpdb->delete(
        $table_name,
        array('id' => $entry_id),
        array('%d')
    );

    if ($result !== false) {
        wp_send_json_success(array(
            'message' => 'Entry deleted successfully',
            'entry_id' => $entry_id
        ));
    } else {
        wp_send_json_error('Failed to delete entry from database');
    }

    exit;
}

/**
 * Handle bulk deletion of knowledge entries via AJAX
 * Supports both Pinecone and WordPress database entries
 */
public function ajax_mxchat_bulk_delete_knowledge() {
    // Verify nonce and permissions
    if (!check_ajax_referer('mxchat_bulk_delete_knowledge_nonce', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        exit;
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
        exit;
    }

    $entries = isset($_POST['entries']) ? $_POST['entries'] : array();
    $bot_id = isset($_POST['bot_id']) ? sanitize_text_field($_POST['bot_id']) : 'default';

    if (empty($entries) || !is_array($entries)) {
        wp_send_json_error('No entries provided');
        exit;
    }

    $success_ids = array();
    $failed_ids = array();
    $errors = array();

    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';

    // Get Pinecone manager for Pinecone deletions
    $pinecone_manager = MxChat_Pinecone_Manager::get_instance();
    $pinecone_options = $pinecone_manager->mxchat_get_bot_pinecone_options($bot_id);
    $use_pinecone = ($pinecone_options['mxchat_use_pinecone'] ?? '0') === '1';

    foreach ($entries as $entry) {
        $entry_id = sanitize_text_field($entry['id'] ?? '');
        $source = sanitize_text_field($entry['source'] ?? 'wordpress');
        $source_url = isset($entry['sourceUrl']) ? esc_url_raw($entry['sourceUrl']) : '';
        $is_group = isset($entry['isGroup']) && ($entry['isGroup'] === true || $entry['isGroup'] === 'true');

        if (empty($entry_id)) {
            continue;
        }

        try {
            if ($source === 'pinecone') {
                // Handle Pinecone deletion
                if (!$use_pinecone || empty($pinecone_options['mxchat_pinecone_api_key'])) {
                    $failed_ids[] = $entry_id;
                    $errors[] = "Pinecone not configured for entry: $entry_id";
                    continue;
                }

                if ($is_group && !empty($source_url)) {
                    // Delete all chunks for this URL
                    $base_vector_id = md5($source_url);
                    $api_key = $pinecone_options['mxchat_pinecone_api_key'];
                    $host = $pinecone_options['mxchat_pinecone_host'];

                    // List all vectors with this prefix
                    $list_url = 'https://' . $host . '/vectors/list?prefix=' . urlencode($base_vector_id . '_chunk_') . '&limit=100';
                    $list_response = wp_remote_get($list_url, array(
                        'headers' => array(
                            'Api-Key' => $api_key,
                            'Content-Type' => 'application/json'
                        ),
                        'timeout' => 30
                    ));

                    $vector_ids = array($base_vector_id); // Include base ID

                    if (!is_wp_error($list_response)) {
                        $list_body = json_decode(wp_remote_retrieve_body($list_response), true);
                        if (isset($list_body['vectors']) && is_array($list_body['vectors'])) {
                            foreach ($list_body['vectors'] as $vector) {
                                if (isset($vector['id'])) {
                                    $vector_ids[] = $vector['id'];
                                }
                            }
                        }
                    }

                    // Delete all vectors
                    $delete_result = $pinecone_manager->mxchat_delete_pinecone_batch(
                        $vector_ids,
                        $api_key,
                        $host
                    );

                    if ($delete_result['success']) {
                        $success_ids[] = $entry_id;
                    } else {
                        $failed_ids[] = $entry_id;
                        $errors[] = $delete_result['message'] ?? "Failed to delete group: $entry_id";
                    }
                } else {
                    // Delete single vector
                    $result = $pinecone_manager->mxchat_delete_from_pinecone_by_vector_id(
                        $entry_id,
                        $pinecone_options['mxchat_pinecone_api_key'],
                        $pinecone_options['mxchat_pinecone_host']
                    );

                    if ($result['success']) {
                        $success_ids[] = $entry_id;
                    } else {
                        $failed_ids[] = $entry_id;
                        $errors[] = $result['message'] ?? "Failed to delete: $entry_id";
                    }
                }
            } else {
                // Handle WordPress database deletion
                if ($is_group && !empty($source_url)) {
                    // Delete all entries with this source URL
                    $result = $wpdb->delete(
                        $table_name,
                        array('source_url' => $source_url),
                        array('%s')
                    );
                } else {
                    // Delete single entry
                    wp_cache_delete('prompt_' . $entry_id, 'mxchat_prompts');
                    $result = $wpdb->delete(
                        $table_name,
                        array('id' => intval($entry_id)),
                        array('%d')
                    );
                }

                if ($result !== false) {
                    $success_ids[] = $entry_id;
                } else {
                    $failed_ids[] = $entry_id;
                    $errors[] = "Database error for entry: $entry_id";
                }
            }
        } catch (Exception $e) {
            $failed_ids[] = $entry_id;
            $errors[] = $e->getMessage();
        }
    }

    wp_send_json_success(array(
        'success_ids' => $success_ids,
        'failed_ids' => $failed_ids,
        'errors' => $errors,
        'total_processed' => count($success_ids) + count($failed_ids)
    ));

    exit;
}

/**
 *   Get hierarchical roles for dropdown
 */
public function mxchat_get_role_options() {
    return array(
        'public' => __('Public (Everyone)', 'mxchat'),
        'logged_in' => __('Logged In Users', 'mxchat'),
        'subscriber' => __('Subscribers & Above', 'mxchat'),
        'contributor' => __('Contributors & Above', 'mxchat'),
        'author' => __('Authors & Above', 'mxchat'),
        'editor' => __('Editors & Above', 'mxchat'),
        'administrator' => __('Administrators Only', 'mxchat')
    );
}

/**
 *   Check if user has access to content based on role restriction
 */
public function mxchat_user_has_content_access($role_restriction) {
    // Public content is always accessible
    if ($role_restriction === 'public' || empty($role_restriction)) {
        return true;
    }
    
    // Check if user is logged in for logged_in restriction
    if ($role_restriction === 'logged_in') {
        return is_user_logged_in();
    }
    
    // If not logged in, no access to role-restricted content
    if (!is_user_logged_in()) {
        return false;
    }
    
    $user = wp_get_current_user();
    $user_roles = $user->roles;
    
    if (empty($user_roles)) {
        return false;
    }
    
    // Define role hierarchy (higher number = higher access)
    $hierarchy = array(
        'subscriber' => 1,
        'contributor' => 2,
        'author' => 3,
        'editor' => 4,
        'administrator' => 5
    );
    
    // Get required level
    $required_level = isset($hierarchy[$role_restriction]) ? $hierarchy[$role_restriction] : 0;
    
    // Check if user has required level or higher
    foreach ($user_roles as $user_role) {
        $user_level = isset($hierarchy[$user_role]) ? $hierarchy[$user_role] : 0;
        if ($user_level >= $required_level) {
            return true;
        }
    }
    
    return false;
}

/**
 * Handle role restriction updates via AJAX
 *  Removed cache clearing call since we removed caching
 */
public function ajax_mxchat_update_role_restriction() {
    // Verify nonce and permissions
    if (!check_ajax_referer('mxchat_update_role_nonce', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        exit;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
        exit;
    }
    
    $entry_id = isset($_POST['entry_id']) ? sanitize_text_field($_POST['entry_id']) : '';
    $role_restriction = isset($_POST['role_restriction']) ? sanitize_text_field($_POST['role_restriction']) : 'public';
    $data_source = isset($_POST['data_source']) ? sanitize_text_field($_POST['data_source']) : 'wordpress';
    
    if (empty($entry_id)) {
        wp_send_json_error('Invalid entry ID');
        exit;
    }
    
    // Get knowledge manager instance to validate role restriction
    $knowledge_manager = MxChat_Knowledge_Manager::get_instance();
    $valid_roles = array_keys($knowledge_manager->mxchat_get_role_options());
    if (!in_array($role_restriction, $valid_roles)) {
        wp_send_json_error('Invalid role restriction');
        exit;
    }
    
    global $wpdb;
    
    if ($data_source === 'pinecone') {
        // Handle Pinecone role restriction (stored separately in WordPress table)
        $roles_table = $wpdb->prefix . 'mxchat_pinecone_roles';
        
        // Use REPLACE to insert or update the role restriction
        $result = $wpdb->replace(
            $roles_table,
            array(
                'vector_id' => $entry_id,
                'role_restriction' => $role_restriction,
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );
        
        // No cache clearing needed since we removed caching
        
    } else {
        // Handle WordPress database role restriction (existing functionality)
        $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';
        
        $result = $wpdb->update(
            $table_name,
            array('role_restriction' => $role_restriction),
            array('id' => absint($entry_id)),
            array('%s'),
            array('%d')
        );
    }
    
    if ($result === false) {
        wp_send_json_error('Database update failed: ' . $wpdb->last_error);
        exit;
    }
    
    wp_send_json_success(array(
        'message' => 'Role restriction updated successfully',
        'role_restriction' => $role_restriction,
        'data_source' => $data_source,
        'entry_id' => $entry_id
    ));
    exit;
}

// ========================================
// ROLE-BASED CONTENT RESTRICTIONS - NEW FUNCTIONS
// Add these to your MxChat_Knowledge_Manager class
// ========================================

/**
 *   Initialize role-based content hooks
 * Add this call to your __construct() or mxchat_init_hooks() method
 */
private function mxchat_init_role_hooks() {
    // AJAX handlers for tag-role mappings
    add_action('wp_ajax_mxchat_add_tag_role_mapping', array($this, 'ajax_add_tag_role_mapping'));
    add_action('wp_ajax_mxchat_delete_tag_role_mapping', array($this, 'ajax_delete_tag_role_mapping'));
    add_action('wp_ajax_mxchat_get_tag_role_mappings', array($this, 'ajax_get_tag_role_mappings'));
    add_action('wp_ajax_mxchat_bulk_update_tag_roles', array($this, 'ajax_bulk_update_tag_roles'));
    
    // Hook to automatically update role restrictions when tags are added/removed
    add_action('set_object_terms', array($this, 'handle_tag_change'), 10, 6);
    
    // Hook to apply role restrictions on auto-sync
    add_action('mxchat_content_stored', array($this, 'apply_role_restriction_after_storage'), 10, 2);
}

/**
 *   Add tag-role mapping via AJAX
 */
public function ajax_add_tag_role_mapping() {
    // Verify nonce and permissions
    check_ajax_referer('mxchat_prompts_setting_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
        exit;
    }
    
    $tag_slug = isset($_POST['tag_slug']) ? sanitize_text_field($_POST['tag_slug']) : '';
    $role_restriction = isset($_POST['role_restriction']) ? sanitize_text_field($_POST['role_restriction']) : 'public';
    
    if (empty($tag_slug)) {
        wp_send_json_error('Tag slug is required');
        exit;
    }
    
    // Validate role restriction
    $valid_roles = array_keys($this->mxchat_get_role_options());
    if (!in_array($role_restriction, $valid_roles)) {
        wp_send_json_error('Invalid role restriction');
        exit;
    }
    
    // Check if tag exists in WordPress
    $term = get_term_by('slug', $tag_slug, 'post_tag');
    if (!$term) {
        wp_send_json_error('Tag does not exist in WordPress');
        exit;
    }
    
    // Get existing mappings
    $mappings = get_option('mxchat_tag_role_mappings', array());
    
    // Check if mapping already exists
    if (isset($mappings[$tag_slug])) {
        wp_send_json_error('Mapping for this tag already exists');
        exit;
    }
    
    // Add new mapping
    $mappings[$tag_slug] = $role_restriction;
    update_option('mxchat_tag_role_mappings', $mappings);
    
    wp_send_json_success(array(
        'message' => 'Tag-role mapping added successfully',
        'tag_slug' => $tag_slug,
        'role_restriction' => $role_restriction
    ));
    exit;
}

/**
 *   Delete tag-role mapping via AJAX
 */
public function ajax_delete_tag_role_mapping() {
    // Verify nonce and permissions
    check_ajax_referer('mxchat_prompts_setting_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
        exit;
    }
    
    $tag_slug = isset($_POST['tag_slug']) ? sanitize_text_field($_POST['tag_slug']) : '';
    
    if (empty($tag_slug)) {
        wp_send_json_error('Tag slug is required');
        exit;
    }
    
    // Get existing mappings
    $mappings = get_option('mxchat_tag_role_mappings', array());
    
    // Check if mapping exists
    if (!isset($mappings[$tag_slug])) {
        wp_send_json_error('Mapping does not exist');
        exit;
    }
    
    // Remove mapping
    unset($mappings[$tag_slug]);
    update_option('mxchat_tag_role_mappings', $mappings);
    
    wp_send_json_success(array(
        'message' => 'Tag-role mapping deleted successfully',
        'tag_slug' => $tag_slug
    ));
    exit;
}

/**
 *   Get all tag-role mappings via AJAX
 */
public function ajax_get_tag_role_mappings() {
    // Verify nonce and permissions
    check_ajax_referer('mxchat_prompts_setting_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
        exit;
    }
    
    // Get mappings
    $mappings = get_option('mxchat_tag_role_mappings', array());
    $role_options = $this->mxchat_get_role_options();
    
    $formatted_mappings = array();
    
    foreach ($mappings as $tag_slug => $role_restriction) {
        // Get tag object
        $term = get_term_by('slug', $tag_slug, 'post_tag');
        
        // Count posts with this tag
        $post_count = 0;
        if ($term) {
            $post_count = $term->count;
        }
        
        $formatted_mappings[] = array(
            'tag_slug' => $tag_slug,
            'role_restriction' => $role_restriction,
            'role_label' => $role_options[$role_restriction] ?? $role_restriction,
            'post_count' => $post_count
        );
    }
    
    wp_send_json_success(array(
        'mappings' => $formatted_mappings
    ));
    exit;
}

/**
 *   Bulk update role restrictions for all existing content with mapped tags
 */
public function ajax_bulk_update_tag_roles() {
    // Verify nonce and permissions
    check_ajax_referer('mxchat_prompts_setting_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
        exit;
    }
    
    // Get mappings
    $mappings = get_option('mxchat_tag_role_mappings', array());
    
    if (empty($mappings)) {
        wp_send_json_error('No tag-role mappings found');
        exit;
    }
    
    global $wpdb;
    
    // Check if using Pinecone
    $pinecone_options = get_option('mxchat_pinecone_addon_options', array());
    $use_pinecone = ($pinecone_options['mxchat_use_pinecone'] ?? '0') === '1';
    
    $updated_count = 0;
    $details = array();
    
    foreach ($mappings as $tag_slug => $role_restriction) {
        // Get all posts with this tag
        $posts = get_posts(array(
            'tag' => $tag_slug,
            'post_type' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_status' => 'publish'
        ));
        
        if (empty($posts)) {
            continue;
        }
        
        $tag_updated = 0;
        
        foreach ($posts as $post_id) {
            $source_url = get_permalink($post_id);
            if (!$source_url) {
                continue;
            }
            
            if ($use_pinecone && !empty($pinecone_options['mxchat_pinecone_api_key'])) {
                // Update Pinecone role restriction
                $roles_table = $wpdb->prefix . 'mxchat_pinecone_roles';
                $vector_id = md5($source_url);
                
                $result = $wpdb->replace(
                    $roles_table,
                    array(
                        'vector_id' => $vector_id,
                        'role_restriction' => $role_restriction,
                        'updated_at' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s')
                );
            } else {
                // Update WordPress DB
                $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';
                
                $result = $wpdb->update(
                    $table_name,
                    array('role_restriction' => $role_restriction),
                    array('source_url' => $source_url),
                    array('%s'),
                    array('%s')
                );
            }
            
            if ($result !== false) {
                $tag_updated++;
                $updated_count++;
            }
        }
        
        if ($tag_updated > 0) {
            $details[] = sprintf(
                'Tag "%s" (%s): %d posts updated',
                $tag_slug,
                $role_restriction,
                $tag_updated
            );
        }
    }
    
    wp_send_json_success(array(
        'message' => 'Bulk update completed',
        'updated_count' => $updated_count,
        'tags_processed' => count($mappings),
        'details' => $details
    ));
    exit;
}

/**
 *   Handle tag changes on posts (when tags are added or removed)
 */
public function handle_tag_change($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
    // Only process post tags
    if ($taxonomy !== 'post_tag') {
        return;
    }
    
    // Get tag-role mappings
    $mappings = get_option('mxchat_tag_role_mappings', array());
    
    if (empty($mappings)) {
        return;
    }
    
    // Get the post's URL
    $source_url = get_permalink($object_id);
    if (!$source_url) {
        return;
    }
    
    // Determine the highest role restriction based on tags
    $highest_role = 'public';
    $role_hierarchy = array(
        'public' => 0,
        'logged_in' => 1,
        'subscriber' => 2,
        'contributor' => 3,
        'author' => 4,
        'editor' => 5,
        'administrator' => 6
    );
    
    // Get all current tags for the post
    $current_tags = wp_get_post_tags($object_id, array('fields' => 'slugs'));
    
    // Find the highest role restriction among the tags
    foreach ($current_tags as $tag_slug) {
        if (isset($mappings[$tag_slug])) {
            $role = $mappings[$tag_slug];
            if (isset($role_hierarchy[$role]) && $role_hierarchy[$role] > $role_hierarchy[$highest_role]) {
                $highest_role = $role;
            }
        }
    }
    
    // Update the role restriction in the database
    global $wpdb;
    
    // Check if using Pinecone
    $pinecone_options = get_option('mxchat_pinecone_addon_options', array());
    $use_pinecone = ($pinecone_options['mxchat_use_pinecone'] ?? '0') === '1';
    
    if ($use_pinecone && !empty($pinecone_options['mxchat_pinecone_api_key'])) {
        // Update Pinecone role restriction
        $roles_table = $wpdb->prefix . 'mxchat_pinecone_roles';
        $vector_id = md5($source_url);
        
        $wpdb->replace(
            $roles_table,
            array(
                'vector_id' => $vector_id,
                'role_restriction' => $highest_role,
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );
    } else {
        // Update WordPress DB
        $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';
        
        $wpdb->update(
            $table_name,
            array('role_restriction' => $highest_role),
            array('source_url' => $source_url),
            array('%s'),
            array('%s')
        );
    }
}

/**
 *   Apply role restriction after content is stored (for auto-sync)
 */
public function apply_role_restriction_after_storage($post_id, $source_url) {
    // Get tag-role mappings
    $mappings = get_option('mxchat_tag_role_mappings', array());
    
    if (empty($mappings)) {
        return;
    }
    
    // Get all tags for the post
    $post_tags = wp_get_post_tags($post_id, array('fields' => 'slugs'));
    
    if (empty($post_tags)) {
        return;
    }
    
    // Determine the highest role restriction based on tags
    $highest_role = 'public';
    $role_hierarchy = array(
        'public' => 0,
        'logged_in' => 1,
        'subscriber' => 2,
        'contributor' => 3,
        'author' => 4,
        'editor' => 5,
        'administrator' => 6
    );
    
    foreach ($post_tags as $tag_slug) {
        if (isset($mappings[$tag_slug])) {
            $role = $mappings[$tag_slug];
            if (isset($role_hierarchy[$role]) && $role_hierarchy[$role] > $role_hierarchy[$highest_role]) {
                $highest_role = $role;
            }
        }
    }
    
    // If no restricted tags found, return (leave as public)
    if ($highest_role === 'public') {
        return;
    }
    
    // Update the role restriction
    global $wpdb;
    
    // Check if using Pinecone
    $pinecone_options = get_option('mxchat_pinecone_addon_options', array());
    $use_pinecone = ($pinecone_options['mxchat_use_pinecone'] ?? '0') === '1';
    
    if ($use_pinecone && !empty($pinecone_options['mxchat_pinecone_api_key'])) {
        // Update Pinecone role restriction
        $roles_table = $wpdb->prefix . 'mxchat_pinecone_roles';
        $vector_id = md5($source_url);
        
        $wpdb->replace(
            $roles_table,
            array(
                'vector_id' => $vector_id,
                'role_restriction' => $highest_role,
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );
    } else {
        // Update WordPress DB
        $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';
        
        $wpdb->update(
            $table_name,
            array('role_restriction' => $highest_role),
            array('source_url' => $source_url),
            array('%s'),
            array('%s')
        );
    }
}


 // ========================================
    // HELPER METHODS
    // ========================================
    
    /**
     * Check if user has required permissions for content processing
     */
    private function mxchat_check_user_permissions() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'mxchat'));
        }
    }
    
    /**
     * Validate nonce for security
     */
    private function mxchat_validate_nonce($nonce_name, $nonce_action) {
        if (!isset($_POST[$nonce_name]) || !wp_verify_nonce($_POST[$nonce_name], $nonce_action)) {
            wp_die(esc_html__('Security check failed.', 'mxchat'));
        }
    }
    
    /**
     * Get embedding API credentials
     */
    private function mxchat_get_embedding_credentials() {
        $embedding_model = $this->options['embedding_model'] ?? 'text-embedding-ada-002';
        
        if (strpos($embedding_model, 'text-embedding-') !== false) {
            return array(
                'type' => 'openai',
                'api_key' => $this->options['api_key'] ?? ''
            );
        } elseif (strpos($embedding_model, 'voyage-') !== false) {
            return array(
                'type' => 'voyage',
                'api_key' => $this->options['voyage_api_key'] ?? ''
            );
        } elseif (strpos($embedding_model, 'gemini-embedding-') !== false) {
            return array(
                'type' => 'gemini',
                'api_key' => $this->options['gemini_api_key'] ?? ''
            );
        }
        
        return array('type' => 'unknown', 'api_key' => '');
    }
    
    /**
     * Log processing errors
     */
    private function mxchat_log_processing_error($operation, $error_message) {
        //error_log("MxChat Knowledge Processing {$operation} Error: " . $error_message);
    }
    
    /**
     * Set admin notice transient
     */
    private function mxchat_set_admin_notice($type, $message) {
        set_transient("mxchat_admin_notice_{$type}", $message, 30);
    }
    
    /**
     * Get Pinecone manager instance for vector operations
     */
    private function mxchat_get_pinecone_manager() {
        return MxChat_Pinecone_Manager::get_instance();
    }
    
    
    // ========================================
// DATABASE QUEUE TABLE MANAGEMENT
// ========================================

/**
 * Create queue table on plugin activation
 * Call this from your plugin activation hook
 */
public function mxchat_create_queue_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'mxchat_processing_queue';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        queue_id varchar(64) NOT NULL,
        item_type varchar(20) NOT NULL,
        item_data longtext NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        bot_id varchar(50) NOT NULL DEFAULT 'default',
        priority int(11) NOT NULL DEFAULT 0,
        attempts int(11) NOT NULL DEFAULT 0,
        max_attempts int(11) NOT NULL DEFAULT 3,
        error_message text DEFAULT NULL,
        created_at datetime NOT NULL,
        started_at datetime DEFAULT NULL,
        completed_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY queue_id (queue_id),
        KEY status (status),
        KEY item_type (item_type),
        KEY priority (priority)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Also create a meta table for queue metadata
    $meta_table = $wpdb->prefix . 'mxchat_queue_meta';
    
    $meta_sql = "CREATE TABLE IF NOT EXISTS $meta_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        queue_id varchar(64) NOT NULL,
        meta_key varchar(255) NOT NULL,
        meta_value longtext,
        PRIMARY KEY  (id),
        KEY queue_id (queue_id),
        KEY meta_key (meta_key)
    ) $charset_collate;";
    
    dbDelta($meta_sql);
}

/**
 * Add items to the processing queue
 * 
 * @param string $queue_id Unique identifier for this queue batch
 * @param string $item_type Type of item (url, pdf_page)
 * @param array $items Array of items to queue
 * @param string $bot_id Bot ID for processing
 * @return int Number of items queued
 */
private function mxchat_add_to_queue($queue_id, $item_type, $items, $bot_id = 'default') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_processing_queue';
    
    $queued_count = 0;
    $priority = 0;
    
    foreach ($items as $item) {
        $result = $wpdb->insert(
            $table_name,
            array(
                'queue_id' => $queue_id,
                'item_type' => $item_type,
                'item_data' => wp_json_encode($item),
                'status' => 'pending',
                'bot_id' => $bot_id,
                'priority' => $priority,
                'attempts' => 0,
                'max_attempts' => 3,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s')
        );
        
        if ($result) {
            $queued_count++;
        }
        
        $priority++; // Process in order
    }
    
    return $queued_count;
}

/**
 * Store queue metadata (total counts, source URL, etc.)
 */
private function mxchat_set_queue_meta($queue_id, $meta_key, $meta_value) {
    global $wpdb;
    $meta_table = $wpdb->prefix . 'mxchat_queue_meta';
    
    // Check if meta exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $meta_table WHERE queue_id = %s AND meta_key = %s",
        $queue_id,
        $meta_key
    ));
    
    if ($existing) {
        // Update
        $wpdb->update(
            $meta_table,
            array('meta_value' => maybe_serialize($meta_value)),
            array('queue_id' => $queue_id, 'meta_key' => $meta_key),
            array('%s'),
            array('%s', '%s')
        );
    } else {
        // Insert
        $wpdb->insert(
            $meta_table,
            array(
                'queue_id' => $queue_id,
                'meta_key' => $meta_key,
                'meta_value' => maybe_serialize($meta_value)
            ),
            array('%s', '%s', '%s')
        );
    }
}

/**
 * Get queue metadata
 */
private function mxchat_get_queue_meta($queue_id, $meta_key) {
    global $wpdb;
    $meta_table = $wpdb->prefix . 'mxchat_queue_meta';
    
    $value = $wpdb->get_var($wpdb->prepare(
        "SELECT meta_value FROM $meta_table WHERE queue_id = %s AND meta_key = %s",
        $queue_id,
        $meta_key
    ));
    
    return maybe_unserialize($value);
}

// ========================================
// AJAX QUEUE PROCESSING HANDLERS
// ========================================

/**
 * AJAX: Get next item from queue to process
 */
public function ajax_mxchat_get_next_queue_item() {
    // Verify nonce and permissions
    check_ajax_referer('mxchat_queue_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
    }
    
    $queue_id = isset($_POST['queue_id']) ? sanitize_text_field($_POST['queue_id']) : '';
    
    if (empty($queue_id)) {
        wp_send_json_error('Missing queue ID');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_processing_queue';
    
    // Get next pending item with retry logic for failed items
    $next_item = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name 
        WHERE queue_id = %s 
        AND status IN ('pending', 'failed')
        AND attempts < max_attempts
        ORDER BY priority ASC, id ASC
        LIMIT 1",
        $queue_id
    ));
    
    if (!$next_item) {
        // No more items - queue complete
        wp_send_json_success(array(
            'complete' => true,
            'message' => 'Queue processing complete'
        ));
    }
    
    // Mark item as processing
    $wpdb->update(
        $table_name,
        array(
            'status' => 'processing',
            'started_at' => current_time('mysql'),
            'attempts' => $next_item->attempts + 1
        ),
        array('id' => $next_item->id),
        array('%s', '%s', '%d'),
        array('%d')
    );
    
    wp_send_json_success(array(
        'complete' => false,
        'item' => array(
            'id' => $next_item->id,
            'type' => $next_item->item_type,
            'data' => json_decode($next_item->item_data, true),
            'bot_id' => $next_item->bot_id,
            'attempt' => $next_item->attempts + 1
        )
    ));
}

/**
 * AJAX: Process a single queue item
 */
public function ajax_mxchat_process_queue_item() {
    // Verify nonce and permissions
    check_ajax_referer('mxchat_queue_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
    }
    
    $item_id = isset($_POST['item_id']) ? absint($_POST['item_id']) : 0;
    $item_type = isset($_POST['item_type']) ? sanitize_text_field($_POST['item_type']) : '';
    $item_data = isset($_POST['item_data']) ? $_POST['item_data'] : array();
    $bot_id = isset($_POST['bot_id']) ? sanitize_key($_POST['bot_id']) : 'default';
    
    if (empty($item_id) || empty($item_type)) {
        wp_send_json_error('Missing item data');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_processing_queue';
    
    // Process based on item type
    try {
        set_time_limit(60); // Give processing 60 seconds
        
        $result = false;
        $error_message = '';
        
        switch ($item_type) {
            case 'url':
                $result = $this->mxchat_process_queue_url($item_data, $bot_id);
                break;
                
            case 'pdf_page':
                $result = $this->mxchat_process_queue_pdf_page($item_data, $bot_id);
                break;
                
            default:
                throw new Exception('Unknown item type: ' . $item_type);
        }
        
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }
        
        if ($result === false) {
            throw new Exception('Processing returned false - item may be empty or invalid');
        }
        
        // Mark as completed
        $wpdb->update(
            $table_name,
            array(
                'status' => 'completed',
                'completed_at' => current_time('mysql'),
                'error_message' => null
            ),
            array('id' => $item_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        wp_send_json_success(array(
            'processed' => true,
            'item_id' => $item_id,
            'message' => 'Item processed successfully'
        ));
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        
        // Get current attempt count
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT attempts, max_attempts FROM $table_name WHERE id = %d",
            $item_id
        ));
        
        // Check if we've exhausted retries
        if ($item && $item->attempts >= $item->max_attempts) {
            // Permanently failed
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $error_message
                ),
                array('id' => $item_id),
                array('%s', '%s'),
                array('%d')
            );
            
            wp_send_json_error(array(
                'message' => 'Item failed after maximum attempts: ' . $error_message,
                'permanent_failure' => true,
                'item_id' => $item_id
            ));
        } else {
            // Mark for retry
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $error_message
                ),
                array('id' => $item_id),
                array('%s', '%s'),
                array('%d')
            );
            
            wp_send_json_error(array(
                'message' => 'Item processing failed, will retry: ' . $error_message,
                'can_retry' => true,
                'item_id' => $item_id,
                'attempts' => $item ? $item->attempts : 0
            ));
        }
    }
}

/**
 * Process a URL from the queue
 */
private function mxchat_process_queue_url($item_data, $bot_id = 'default') {
    $url = isset($item_data['url']) ? $item_data['url'] : '';

    if (empty($url)) {
        return new WP_Error('invalid_url', 'URL is empty');
    }

    // Get bot-specific API key early (needed for both paths)
    $bot_options = $this->get_bot_options($bot_id);
    $options = !empty($bot_options) ? $bot_options : get_option('mxchat_options');
    $selected_model = $options['embedding_model'] ?? 'text-embedding-ada-002';

    if (strpos($selected_model, 'voyage') === 0) {
        $api_key = $options['voyage_api_key'] ?? '';
    } elseif (strpos($selected_model, 'gemini-embedding') === 0) {
        $api_key = $options['gemini_api_key'] ?? '';
    } else {
        $api_key = $options['api_key'] ?? '';
    }

    if (empty($api_key)) {
        return new WP_Error('no_api_key', 'API key not configured for bot: ' . $bot_id);
    }

    // Check if this is a WooCommerce product URL and WooCommerce is active
    $is_product_url = (strpos($url, '/product/') !== false || strpos($url, '/shop/') !== false);
    $content_type = $is_product_url ? 'product' : 'url';

    // Try to get WooCommerce product data if it's a product URL
    if ($is_product_url && class_exists('WooCommerce')) {
        $product_content = $this->mxchat_extract_woocommerce_product_content($url);

        if (!empty($product_content)) {
            // Successfully extracted WooCommerce product data with pricing
            $result = MxChat_Utils::submit_content_to_db(
                $product_content,
                $url,
                $api_key,
                null,
                $bot_id,
                'product'
            );
            return $result;
        }
        // If WooCommerce extraction failed, fall through to HTML extraction
    }

    // Fetch URL content (fallback for non-products or when WooCommerce extraction fails)
    $response = wp_remote_get($url, array(
        'timeout' => 30,
        'redirection' => 5,
        'user-agent' => 'MxChat/1.0'
    ));

    if (is_wp_error($response)) {
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        return new WP_Error('http_error', 'HTTP ' . $response_code . ' error');
    }

    $html = wp_remote_retrieve_body($response);

    if (empty($html)) {
        return new WP_Error('empty_response', 'Empty response body');
    }

    // Extract and sanitize content
    $content = $this->mxchat_extract_main_content($html);
    $sanitized = $this->mxchat_sanitize_content_for_api($content);

    if (empty($sanitized)) {
        // Not an error - just no content found (maybe a redirect or empty page)
        return false;
    }

    // Submit to database with content_type
    $result = MxChat_Utils::submit_content_to_db(
        $sanitized,
        $url,
        $api_key,
        null,
        $bot_id,
        $content_type
    );

    return $result;
}

/**
 * Extract WooCommerce product content including pricing
 *
 * @param string $url The product URL
 * @return string|false Product content with pricing, or false if not found
 */
private function mxchat_extract_woocommerce_product_content($url) {
    // Try to get product ID from URL
    $product_id = url_to_postid($url);

    // If url_to_postid fails, try to extract from URL pattern
    if (!$product_id) {
        $product_slug = '';

        // Handle pretty permalinks: /product/product-name/
        if (preg_match('/\/product\/([^\/\?]+)/', $url, $matches)) {
            $product_slug = $matches[1];
        }

        if (!empty($product_slug)) {
            $product_post = get_page_by_path($product_slug, OBJECT, 'product');
            if ($product_post) {
                $product_id = $product_post->ID;
            }
        }
    }

    if (!$product_id) {
        return false;
    }

    // Get WooCommerce product object
    $product = wc_get_product($product_id);

    if (!$product) {
        return false;
    }

    // Build product content with pricing (similar to mxchat_store_product_embedding)
    $title = $product->get_name();
    $description = $product->get_description();
    $short_description = $product->get_short_description();
    $sku = $product->get_sku();

    // Get pricing information
    $regular_price = $product->get_regular_price();
    $sale_price = $product->get_sale_price();
    $price = $product->get_price(); // Current active price

    // Get currency symbol
    $currency_symbol = get_woocommerce_currency_symbol();

    // Format content
    $content = $title . "\n\n";

    if (!empty($short_description)) {
        $content .= "Short Description: " . wp_strip_all_tags($short_description) . "\n\n";
    }

    if (!empty($description)) {
        $content .= wp_strip_all_tags($description) . "\n\n";
    }

    // Add pricing information
    if (!empty($regular_price)) {
        $content .= "Price: " . $currency_symbol . $regular_price . "\n";
    } elseif (!empty($price)) {
        $content .= "Price: " . $currency_symbol . $price . "\n";
    }

    if (!empty($sale_price) && $sale_price !== $regular_price) {
        $content .= "Sale Price: " . $currency_symbol . $sale_price . "\n";
    }

    // Handle variable products - show price range
    if ($product->is_type('variable')) {
        $min_price = $product->get_variation_price('min');
        $max_price = $product->get_variation_price('max');
        if ($min_price !== $max_price) {
            $content .= "Price Range: " . $currency_symbol . $min_price . " - " . $currency_symbol . $max_price . "\n";
        }
    }

    if (!empty($sku)) {
        $content .= "SKU: " . $sku . "\n";
    }

    // Get product categories
    $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'));
    if (!empty($categories) && !is_wp_error($categories)) {
        $content .= "Categories: " . implode(', ', $categories) . "\n";
    }

    // Get Custom Product Tabs (supports "Custom Product Tabs for WooCommerce" by Code Parrots)
    $custom_tabs = get_post_meta($product_id, 'yikes_woo_products_tabs', true);
    if (!empty($custom_tabs) && is_array($custom_tabs)) {
        foreach ($custom_tabs as $tab) {
            $tab_title = isset($tab['title']) ? $tab['title'] : (isset($tab['tab_title']) ? $tab['tab_title'] : '');
            $tab_content = isset($tab['content']) ? $tab['content'] : '';

            if (!empty($tab_title) && !empty($tab_content)) {
                $content .= "\n" . $tab_title . ": " . wp_strip_all_tags($tab_content) . "\n";
            }
        }
    }

    // Also check for reusable/saved tabs applied to this product
    $applied_saved_tabs = get_post_meta($product_id, 'yikes_woo_reusable_products_tabs_applied', true);
    if (!empty($applied_saved_tabs) && is_array($applied_saved_tabs)) {
        $saved_tabs = get_option('yikes_woo_reusable_products_tabs', array());
        if (!empty($saved_tabs) && is_array($saved_tabs)) {
            foreach ($applied_saved_tabs as $saved_tab_id) {
                if (isset($saved_tabs[$saved_tab_id])) {
                    $tab = $saved_tabs[$saved_tab_id];
                    $tab_title = isset($tab['title']) ? $tab['title'] : (isset($tab['tab_title']) ? $tab['tab_title'] : '');
                    $tab_content = isset($tab['content']) ? $tab['content'] : '';

                    if (!empty($tab_title) && !empty($tab_content)) {
                        $content .= "\n" . $tab_title . ": " . wp_strip_all_tags($tab_content) . "\n";
                    }
                }
            }
        }
    }

    return $this->mxchat_sanitize_content_for_api($content);
}

/**
 * Process a PDF page from the queue
 */
private function mxchat_process_queue_pdf_page($item_data, $bot_id = 'default') {
    $pdf_path = isset($item_data['pdf_path']) ? $item_data['pdf_path'] : '';
    $pdf_url = isset($item_data['pdf_url']) ? $item_data['pdf_url'] : '';
    $page_number = isset($item_data['page_number']) ? absint($item_data['page_number']) : 0;
    $total_pages = isset($item_data['total_pages']) ? absint($item_data['total_pages']) : 0;
    
    if (empty($pdf_path) || !file_exists($pdf_path)) {
        return new WP_Error('pdf_not_found', 'PDF file not found: ' . $pdf_path);
    }
    
    if ($page_number < 1) {
        return new WP_Error('invalid_page', 'Invalid page number');
    }
    
    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($pdf_path);
        $pages = $pdf->getPages();
        
        if (!isset($pages[$page_number - 1])) {
            return new WP_Error('page_not_found', 'Page ' . $page_number . ' not found in PDF');
        }
        
        $text = $pages[$page_number - 1]->getText();
        
        if (empty($text)) {
            // Not an error - just an empty page
            return false;
        }
        
        $sanitized = $this->mxchat_sanitize_content_for_api($text);
        
        if (empty($sanitized)) {
            return false;
        }
        
        // Create metadata
        $metadata = array(
            'document_type' => 'pdf',
            'total_pages' => $total_pages,
            'current_page' => $page_number,
            'source_url' => $pdf_url
        );
        
        $content_with_metadata = wp_json_encode($metadata) . "\n---\n" . $sanitized;
        $page_url = esc_url($pdf_url . "#page=" . $page_number);
        
        // Get bot-specific API key
        $bot_options = $this->get_bot_options($bot_id);
        $options = !empty($bot_options) ? $bot_options : get_option('mxchat_options');
        $selected_model = $options['embedding_model'] ?? 'text-embedding-ada-002';
        
        if (strpos($selected_model, 'voyage') === 0) {
            $api_key = $options['voyage_api_key'] ?? '';
        } elseif (strpos($selected_model, 'gemini-embedding') === 0) {
            $api_key = $options['gemini_api_key'] ?? '';
        } else {
            $api_key = $options['api_key'] ?? '';
        }
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'API key not configured for bot: ' . $bot_id);
        }

        // Submit to database - UPDATED 2.5.6: Added content_type 'pdf'
        $result = MxChat_Utils::submit_content_to_db(
            $content_with_metadata,
            $page_url,
            $api_key,
            null,
            $bot_id,
            'pdf'
        );

        return $result;
        
    } catch (Exception $e) {
        return new WP_Error('pdf_parse_error', 'Error parsing PDF: ' . $e->getMessage());
    }
}

/**
 * AJAX: Get queue processing status
 */
public function ajax_mxchat_get_queue_status() {
    // Verify nonce and permissions
    check_ajax_referer('mxchat_queue_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
    }
    
    $queue_id = isset($_POST['queue_id']) ? sanitize_text_field($_POST['queue_id']) : '';
    
    if (empty($queue_id)) {
        wp_send_json_error('Missing queue ID');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_processing_queue';
    
    // Get counts by status
    $counts = $wpdb->get_results($wpdb->prepare(
        "SELECT status, COUNT(*) as count 
        FROM $table_name 
        WHERE queue_id = %s 
        GROUP BY status",
        $queue_id
    ), OBJECT_K);
    
    $total = 0;
    $completed = 0;
    $failed = 0;
    $processing = 0;
    $pending = 0;
    
    foreach ($counts as $status => $data) {
        $count = absint($data->count);
        $total += $count;
        
        switch ($status) {
            case 'completed':
                $completed = $count;
                break;
            case 'failed':
                $failed = $count;
                break;
            case 'processing':
                $processing = $count;
                break;
            case 'pending':
                $pending = $count;
                break;
        }
    }
    
    // Calculate percentage
    $percentage = $total > 0 ? round((($completed + $failed) / $total) * 100) : 0;
    
    // Get failed items details
    $failed_items = array();
    if ($failed > 0) {
        $failed_items = $wpdb->get_results($wpdb->prepare(
            "SELECT item_type, item_data, error_message, attempts 
            FROM $table_name 
            WHERE queue_id = %s 
            AND status = 'failed'
            AND attempts >= max_attempts
            ORDER BY id DESC
            LIMIT 50",
            $queue_id
        ));
    }
    
    // Get queue metadata
    $source_url = $this->mxchat_get_queue_meta($queue_id, 'source_url');
    $queue_type = $this->mxchat_get_queue_meta($queue_id, 'queue_type');
    
    // Determine if queue is complete
    $is_complete = ($pending === 0 && $processing === 0);
    
    wp_send_json_success(array(
        'queue_id' => $queue_id,
        'queue_type' => $queue_type,
        'source_url' => $source_url,
        'total' => $total,
        'completed' => $completed,
        'failed' => $failed,
        'processing' => $processing,
        'pending' => $pending,
        'percentage' => $percentage,
        'is_complete' => $is_complete,
        'failed_items' => $failed_items,
        'status' => $is_complete ? 'complete' : 'processing'
    ));
}

/**
 * AJAX: Clear completed queue
 */
public function ajax_mxchat_clear_queue() {
    // Verify nonce and permissions
    check_ajax_referer('mxchat_queue_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
    }
    
    $queue_id = isset($_POST['queue_id']) ? sanitize_text_field($_POST['queue_id']) : '';
    
    if (empty($queue_id)) {
        wp_send_json_error('Missing queue ID');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_processing_queue';
    $meta_table = $wpdb->prefix . 'mxchat_queue_meta';
    
    // Delete queue items
    $wpdb->delete(
        $table_name,
        array('queue_id' => $queue_id),
        array('%s')
    );
    
    // Delete queue metadata
    $wpdb->delete(
        $meta_table,
        array('queue_id' => $queue_id),
        array('%s')
    );
    
    wp_send_json_success(array(
        'message' => 'Queue cleared successfully'
    ));
}

/**
 * AJAX: Retry failed items in queue
 */
public function ajax_mxchat_retry_failed() {
    // Verify nonce and permissions
    check_ajax_referer('mxchat_queue_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
    }
    
    $queue_id = isset($_POST['queue_id']) ? sanitize_text_field($_POST['queue_id']) : '';
    
    if (empty($queue_id)) {
        wp_send_json_error('Missing queue ID');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_processing_queue';
    
    // Reset failed items to pending and reset attempt count
    $updated = $wpdb->update(
        $table_name,
        array(
            'status' => 'pending',
            'attempts' => 0,
            'error_message' => null
        ),
        array(
            'queue_id' => $queue_id,
            'status' => 'failed'
        ),
        array('%s', '%d', '%s'),
        array('%s', '%s')
    );
    
    wp_send_json_success(array(
        'message' => 'Reset ' . $updated . ' failed items for retry',
        'reset_count' => $updated
    ));
}


public function ajax_mxchat_mark_queue_complete() {
    check_ajax_referer('mxchat_queue_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized access');
    }
    
    $queue_id = isset($_POST['queue_id']) ? sanitize_text_field($_POST['queue_id']) : '';
    
    if (empty($queue_id)) {
        wp_send_json_error('Missing queue ID');
    }
    
    // Clear active queue transients
    if (strpos($queue_id, 'sitemap_') === 0) {
        delete_transient('mxchat_active_queue_sitemap');
    } else if (strpos($queue_id, 'pdf_') === 0) {
        delete_transient('mxchat_active_queue_pdf');
    }
    
    wp_send_json_success(array('message' => 'Queue marked as complete'));
}

    
    // ========================================
    // STATIC ACCESS METHODS
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

// Initialize the Knowledge manager
$mxchat_knowledge_manager = MxChat_Knowledge_Manager::get_instance();