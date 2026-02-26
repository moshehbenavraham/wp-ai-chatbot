<?php
if (!defined('ABSPATH')) {
    exit;
}

class MxChat_Integrator {
    private $options;
    private $prompts_options;
    private $chat_count;
    private $fallbackResponse;
    private $productCardHtml;
    private $word_handler;
    private $last_similarity_analysis = null;
    private $current_valid_urls = [];
    private $is_streaming = false; // ADDED: Track if current request is streaming
    private $streaming_headers_sent = false; // Track if streaming headers have been sent

/**
 * Setup streaming headers - call this right before actually streaming
 * This delays header setup to allow actions/forms to return JSON responses
 */
private function setup_streaming_headers() {
    if ($this->streaming_headers_sent || headers_sent()) {
        return false;
    }

    // Disable output buffering
    while (ob_get_level()) {
        ob_end_flush();
    }

    // Set headers for SSE
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    ob_implicit_flush(true);
    flush();

    $this->streaming_headers_sent = true;
    return true;
}

/**
 * Class constructor
 */
public function __construct() {
    $this->options = get_option('mxchat_options');
    $this->prompts_options = get_option('mxchat_prompts_options', array());
    $this->chat_count = get_option('mxchat_chat_count', 0);
    $this->word_handler = new MXChat_Word_Handler($this->options);
    
    // Add all action hooks
    add_action('wp_enqueue_scripts', array($this, 'mxchat_enqueue_scripts_styles'));
    add_action('wp_ajax_mxchat_handle_chat_request', array($this, 'mxchat_handle_chat_request'));
    add_action('wp_ajax_nopriv_mxchat_handle_chat_request', array($this, 'mxchat_handle_chat_request'));
    add_action('wp_ajax_mxchat_dismiss_pre_chat_message', array($this, 'mxchat_dismiss_pre_chat_message'));
    add_action('wp_ajax_nopriv_mxchat_dismiss_pre_chat_message', array($this, 'mxchat_dismiss_pre_chat_message'));
    
    // Add the AJAX actions for checking if the pre-chat message was dismissed
    add_action('wp_ajax_mxchat_check_pre_chat_message_status', array($this, 'mxchat_check_pre_chat_message_status'));
    add_action('wp_ajax_nopriv_mxchat_check_pre_chat_message_status', array($this, 'mxchat_check_pre_chat_message_status'));
    add_action('wp_ajax_mxchat_fetch_conversation_history', [$this, 'mxchat_fetch_conversation_history']);
    add_action('wp_ajax_nopriv_mxchat_fetch_conversation_history', [$this, 'mxchat_fetch_conversation_history']);
    add_action('wp_ajax_mxchat_add_to_cart', [$this, 'mxchat_add_to_cart']);
    add_action('wp_ajax_nopriv_mxchat_add_to_cart', [$this, 'mxchat_add_to_cart']);
    
    // Add REST API routes registration
    add_action('rest_api_init', array($this, 'register_routes'));
    add_action('wp_ajax_mxchat_fetch_new_messages', array($this, 'mxchat_fetch_new_messages'));
    add_action('wp_ajax_nopriv_mxchat_fetch_new_messages', array($this, 'mxchat_fetch_new_messages'));
    
    // Rate limit action - notice we removed the old schedule setup
    add_action('mxchat_reset_rate_limits', array($this, 'mxchat_reset_rate_limits'));
    
    // File upload and handling actions
    add_action('wp_ajax_mxchat_upload_pdf', [$this, 'handle_pdf_upload']);
    add_action('wp_ajax_nopriv_mxchat_upload_pdf', [$this, 'handle_pdf_upload']);
    add_action('wp_ajax_mxchat_remove_pdf', [$this, 'handle_pdf_remove']);
    add_action('wp_ajax_nopriv_mxchat_remove_pdf', [$this, 'handle_pdf_remove']);
    
    // Word document handling actions
    add_action('wp_ajax_mxchat_upload_word', array($this, 'mxchat_handle_word_upload'));
    add_action('wp_ajax_nopriv_mxchat_upload_word', array($this, 'mxchat_handle_word_upload'));
    add_action('wp_ajax_mxchat_remove_word', array($this, 'mxchat_handle_word_remove'));
    add_action('wp_ajax_nopriv_mxchat_remove_word', array($this, 'mxchat_handle_word_remove'));
    add_action('wp_ajax_mxchat_check_word_status', array($this, 'mxchat_check_word_status'));
    add_action('wp_ajax_nopriv_mxchat_check_word_status', array($this, 'mxchat_check_word_status'));
    
    // Email handling actions
    add_action('wp_ajax_nopriv_mxchat_handle_save_email_and_response', [$this, 'mxchat_handle_save_email_and_response']);
    add_action('wp_ajax_mxchat_handle_save_email_and_response', [$this, 'mxchat_handle_save_email_and_response']);
    add_action('wp_ajax_nopriv_mxchat_check_email_provided', [$this, 'mxchat_check_email_provided']);
    add_action('wp_ajax_mxchat_check_email_provided', [$this, 'mxchat_check_email_provided']);
    
    add_action('wp_ajax_mxchat_stream_chat', array($this, 'mxchat_handle_chat_request'));
    add_action('wp_ajax_nopriv_mxchat_stream_chat', array($this, 'mxchat_handle_chat_request'));
    
    // Testing panel AJAX actions
    add_action('wp_ajax_mxchat_get_system_info', array($this, 'mxchat_get_system_info'));
    add_action('wp_ajax_mxchat_get_similarity_threshold', array($this, 'mxchat_get_similarity_threshold'));
    add_action('wp_ajax_mxchat_get_kb_status', array($this, 'mxchat_get_kb_status'));
    add_action('wp_ajax_mxchat_start_fresh_session', array($this, 'mxchat_start_fresh_session'));
    // Add to your existing constructor, in the section with other AJAX actions:
    add_action('wp_ajax_mxchat_track_url_click', array($this, 'mxchat_track_url_click'));
    add_action('wp_ajax_nopriv_mxchat_track_url_click', array($this, 'mxchat_track_url_click'));
    add_action('wp_ajax_mxchat_track_originating_page', array($this, 'mxchat_track_originating_page'));
        add_action('wp_ajax_nopriv_mxchat_track_originating_page', array($this, 'mxchat_track_originating_page'));
        // Add chat mode checking actions
        add_action('wp_ajax_mxchat_get_current_chat_mode', array($this, 'mxchat_get_current_chat_mode'));
        add_action('wp_ajax_nopriv_mxchat_get_current_chat_mode', array($this, 'mxchat_get_current_chat_mode'));
    
    // Auto-email transcript action
    add_action('mxchat_send_delayed_transcript', array($this, 'mxchat_send_delayed_transcript'), 10, 1);
    
    add_filter('mxchat_check_actions_only', array($this, 'check_actions_for_addons'), 10, 4);


}

// In your core plugin's check_actions_for_addons method:
public function check_actions_for_addons($default, $message, $user_id, $session_id) {
    //error_log('MxChat Core: check_actions_for_addons called with message: ' . $message);
    
    $result = $this->mxchat_check_intent_and_invoke_callback($message, $user_id, $session_id);
    
    //error_log('MxChat Core: Intent check result = ' . ($result === false ? 'false' : 'true'));
    
    return $result;
}

    private function mxchat_increment_chat_count() {
        $chat_count = get_option('mxchat_chat_count', 0);
        $chat_count++;
        update_option('mxchat_chat_count', $chat_count);
    }

function mxchat_fetch_conversation_history() {
    if (empty($_POST['session_id'])) {
        wp_send_json_error(['message' => esc_html__('Session ID missing.', 'mxchat')]);
        wp_die();
    }

    $session_id = sanitize_text_field($_POST['session_id']);
    
    // SECURITY FIX: Verify session ownership before retrieving data
    // If IP/user changed, signal frontend to reset session instead of blocking
    $current_user_identifier = MxChat_User::mxchat_get_user_identifier();

    // Check if this session has an owner recorded
    $session_owner = get_option("mxchat_session_owner_{$session_id}");

    // If session has an owner and it doesn't match current user, trigger session reset
    if ($session_owner && $session_owner !== $current_user_identifier) {
        wp_send_json_error([
            'message' => esc_html__('Your session has expired. Starting a new conversation.', 'mxchat'),
            'code' => 'session_expired',
            'action' => 'reset_session'
        ]);
        wp_die();
    }
    
    // If no owner is set yet, claim ownership (for legacy sessions)
    if (!$session_owner) {
        update_option("mxchat_session_owner_{$session_id}", $current_user_identifier, 'no');
    }
    
    $history = get_option("mxchat_history_{$session_id}", []); // Retrieve stored history
    $chat_mode = get_option("mxchat_mode_{$session_id}", 'ai'); // Get current chat mode

    if (empty($history)) {
        // Even if history is empty, return the chat mode
        wp_send_json_success([
            'conversation' => [],
            'chat_mode' => $chat_mode
        ]);
        wp_die();
    }

    wp_send_json_success([
        'conversation' => $history,
        'chat_mode' => $chat_mode
    ]);
    wp_die();
}
private function mxchat_fetch_conversation_history_for_ai($session_id, $session_start_timestamp = 0) {
    $history = get_option("mxchat_history_{$session_id}", []);

    // Check persistence setting - when OFF, only include messages from current page load
    $options = get_option('mxchat_options', []);
    $persistence_enabled = isset($options['chat_persistence_toggle']) && $options['chat_persistence_toggle'] === 'on';

    // Filter history when persistence is OFF to match what the user sees
    if (!$persistence_enabled && $session_start_timestamp > 0) {
        $history = array_filter($history, function($entry) use ($session_start_timestamp) {
            // Include messages from this page load onwards
            return isset($entry['timestamp']) && $entry['timestamp'] >= $session_start_timestamp;
        });
        // Re-index array after filtering
        $history = array_values($history);
    }

    $formatted_history = [];

    // Adjusted for code-heavy conversations
    $max_tokens = 120000;    // Context window size
    $reserved_tokens = 5000; // Space for system prompts + current query
    $current_token_count = 0;

    // Allowed HTML tags for content sanitization
    $allowed_tags = [
        'pre' => ['class' => true],
        'code' => ['class' => true],
        'span' => ['class' => true],
        'div' => ['class' => true],
        'strong' => [],
        'em' => []
    ];

    foreach (array_reverse($history) as $entry) {
        // Preserve code blocks while sanitizing other HTML
        $clean_content = wp_kses($entry['content'], $allowed_tags);

        // Detect code blocks in content
        $has_code = false;
// Replace the HTML check with:
// Allow messages that contain code blocks or are plain text
if (strpos($clean_content, '<pre') === false &&
    strpos($clean_content, '<code') === false &&
    $clean_content !== strip_tags($entry['content'])) {
    continue;
}

        // Skip entries that lost significant content during sanitization
        if (!$has_code && $clean_content !== strip_tags($entry['content'])) {
            continue;
        }

        // More accurate token estimation (1 token â‰ˆ 4 characters)
        $token_estimate = ceil(mb_strlen($clean_content, 'UTF-8') / 4);

        // Check token budget with the new estimate
        if (($current_token_count + $token_estimate + $reserved_tokens) > $max_tokens) {
            // Try to fit partial content if it's the first entry
            if (empty($formatted_history)) {
                $clean_content = mb_substr($clean_content, 0, ($max_tokens - $reserved_tokens) * 4);
                $token_estimate = ceil(mb_strlen($clean_content, 'UTF-8') / 4);
            } else {
                break;
            }
        }

        // Add to formatted history
        $formatted_history[] = [
            'role' => $entry['role'],
            'content' => $clean_content
        ];

        $current_token_count += $token_estimate;
    }

    // Reverse back to maintain chronological order
    $formatted_history = array_reverse($formatted_history);

    // Add system message about code context
    array_unshift($formatted_history, [
        'role' => 'system',
        'content' => 'Preserved code blocks are marked with [CODE BLOCK PRESERVED]. '
                    . 'Maintain formatting and syntax highlighting when referencing code.'
    ]);

    return $formatted_history;
}

public function register_routes() {
    //error_log(esc_html__('Registering MxChat REST routes', 'mxchat'));

    register_rest_route('mxchat/v1', '/stream', [
        'methods'  => 'GET',
        'callback' => [$this, 'mxchat_stream_events'],
        'permission_callback' => [$this, 'verify_chat_session'],
    ]);

    register_rest_route('mxchat/v1', '/agent-response', [
        'methods'  => 'POST',
        'callback' => [$this, 'mxchat_handle_agent_response'],
        'permission_callback' => [$this, 'verify_slack_request'],
    ]);

    register_rest_route('mxchat/v1', '/slack-interaction', [
        'methods'  => 'POST',
        'callback' => [$this, 'handle_slack_interaction'],
        'permission_callback' => [$this, 'verify_slack_request'],
    ]);
    
    register_rest_route('mxchat/v1', '/slack-messages', [
        'methods'  => 'POST',
        'callback' => [$this, 'handle_slack_messages'],
        'permission_callback' => [$this, 'verify_slack_request'],
    ]);

    // Telegram webhook endpoint
    register_rest_route('mxchat/v1', '/telegram-webhook', [
        'methods'  => 'POST',
        'callback' => [$this, 'handle_telegram_webhook'],
        'permission_callback' => [$this, 'verify_telegram_request'],
    ]);

    //error_log(esc_html__('MxChat REST routes registered', 'mxchat'));
}

/**
 * Verify valid chat session
 */
public function verify_chat_session($request) {
    $session_id = $request->get_param('session_id');
    if (empty($session_id)) {
        //error_log(esc_html__('Empty session ID in chat request', 'mxchat'));
        return false;
    }

    $chat_mode = get_option("mxchat_mode_{$session_id}", 'ai');
    return $chat_mode === 'agent';
}

/**
 * Verify request is coming from Slack.
 *
 * @param WP_REST_Request $request
 * @return bool True if valid, false otherwise.
 */
public function verify_slack_request($request) {
    // Get the Slack signing secret from your plugin options
    $valid_key = $this->options['live_agent_secret_key'] ?? '';

    if (empty($valid_key)) {
        //error_log(esc_html__('Slack signing secret not configured', 'mxchat'));
        return false;
    }

    $timestamp = $request->get_header('X-Slack-Request-Timestamp');
    $slack_signature = $request->get_header('X-Slack-Signature');

    // Verify timestamp to prevent replay attacks
    if (abs(time() - intval($timestamp)) > 300) {
        //error_log(esc_html__('Slack request timestamp too old', 'mxchat'));
        return false;
    }

    // Get raw request body
    $request_body = file_get_contents('php://input');

    // Create the signature base string
    $sig_basestring = "v0:{$timestamp}:{$request_body}";

    // Calculate expected signature
    $my_signature = 'v0=' . hash_hmac('sha256', $sig_basestring, $valid_key);

    // Compare signatures
    return hash_equals($my_signature, $slack_signature);
}

/**
 * Verify request is coming from Telegram.
 *
 * @param WP_REST_Request $request
 * @return bool True if valid, false otherwise.
 */
public function verify_telegram_request($request) {
    $secret_token = $this->options['telegram_webhook_secret'] ?? '';

    error_log('[MxChat Telegram DEBUG] verify_telegram_request called');
    error_log('[MxChat Telegram DEBUG] Stored secret: ' . (empty($secret_token) ? 'EMPTY' : substr($secret_token, 0, 10) . '...'));

    if (empty($secret_token)) {
        // If no secret is configured, allow the request (for initial setup)
        error_log('[MxChat Telegram DEBUG] No secret configured, allowing request');
        return true;
    }

    // Telegram sends the secret token in the X-Telegram-Bot-Api-Secret-Token header
    $request_token = $request->get_header('X-Telegram-Bot-Api-Secret-Token');

    error_log('[MxChat Telegram DEBUG] Request token: ' . (empty($request_token) ? 'EMPTY' : substr($request_token, 0, 10) . '...'));

    if (empty($request_token)) {
        error_log('[MxChat Telegram DEBUG] Request rejected: No token in header');
        return false;
    }

    // Timing-safe comparison
    $result = hash_equals($secret_token, $request_token);
    error_log('[MxChat Telegram DEBUG] Token comparison result: ' . ($result ? 'MATCH' : 'MISMATCH'));
    return $result;
}

public function mxchat_stream_events(WP_REST_Request $request) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    $session_id = sanitize_text_field($request->get_param('session_id'));
    $last_seen_id = sanitize_text_field($request->get_param('last_seen_id')) ?: '';

    if (empty($session_id)) {
        echo esc_html__("event: error\ndata: ", 'mxchat') . esc_html__('Missing session_id', 'mxchat') . "\n\n";
        flush();
        exit;
    }

    $history = get_option("mxchat_history_{$session_id}", []);

    // Filter only new messages
    $new_messages = array_filter($history, function ($message) use ($last_seen_id) {
        return !empty($message['id']) && $message['id'] > $last_seen_id;
    });

    // Send new messages if available
    if (!empty($new_messages)) {
        echo esc_html__("event: newMessages\ndata: ", 'mxchat') . json_encode(array_values($new_messages)) . "\n\n";
    } else {
        // Keep the connection alive
        echo esc_html__("event: keepAlive\ndata: ", 'mxchat') . "{}\n\n";
    }
    flush();
    exit;
}




private function mxchat_save_chat_message($session_id, $role, $message, $originating_page = null, $rag_context = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_chat_transcripts';
    //error_log("[DEBUG] mxchat_save_chat_message -> START for session_id: {$session_id}, role: {$role}");
    
    // Check if this is the first message in a new session (before any other database operations)
    $is_new_session = false;
    if ($role === 'user') { // Only check for user messages, not bot responses
        $existing_messages = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE session_id = %s",
            $session_id
        ));
        $is_new_session = ($existing_messages == 0);
        
        //   Log for debugging
        if ($is_new_session) {
            //error_log("[DEBUG] This is a NEW session - first message");
        }
    }
    
    // SECURITY FIX: Set session ownership for new sessions
    if ($is_new_session && $role === 'user') {
        $current_user_identifier = MxChat_User::mxchat_get_user_identifier();
        $session_owner_key = "mxchat_session_owner_{$session_id}";
        
        // Only set ownership if not already set
        if (!get_option($session_owner_key)) {
            update_option($session_owner_key, $current_user_identifier, 'no');
            //error_log("[DEBUG] Set session ownership for {$session_id} to {$current_user_identifier}");
        }
    }
    
    // 1) Extract agent name if present
    $agent_name = '';
    if (preg_match('/^Agent: (.*?) - /', $message, $matches)) {
        $agent_name = $matches[1];
        $message    = str_replace("Agent: $agent_name - ", '', $message);
        $session_meta_key = "mxchat_agent_name_{$session_id}";
        if (empty(get_option($session_meta_key))) {
            update_option($session_meta_key, $agent_name);
            //error_log("[DEBUG] mxchat_save_chat_message -> Stored agent_name in option: {$session_meta_key} => {$agent_name}");
        }
    }
    
    // 2) Generate unique message_id
    $message_id = uniqid();
    //error_log("[DEBUG] mxchat_save_chat_message -> Generated message_id: {$message_id}");
    
    // 3) Determine user_id
    $user_id = is_user_logged_in() ? get_current_user_id() : 0;
    
    // 4) Determine user_identifier
    $user_identifier = $agent_name
        ? $agent_name
        : MxChat_User::mxchat_get_user_identifier();
    
    // 5) Determine displayed_name
    $user_email = MxChat_User::mxchat_get_user_email();
    $displayed_name = $agent_name ? $agent_name : ($user_email ?: $user_identifier);
    
    // 6) Check for a saved email in wp_options
    $email_option_key = "mxchat_email_{$session_id}";
    $saved_email = get_option($email_option_key);
    //error_log("[DEBUG] mxchat_save_chat_message -> Checking wp_options for email_option_key: {$email_option_key}, found: {$saved_email}");
    
    //   Check for a saved name in wp_options
    $name_option_key = "mxchat_name_{$session_id}";
    $saved_name = get_option($name_option_key);
    //error_log("[DEBUG] mxchat_save_chat_message -> Checking wp_options for name_option_key: {$name_option_key}, found: {$saved_name}");
    
    // If found, update DB user_email and user_name
    if ($saved_email || $saved_name) {
        $update_data = [];
        if ($saved_email) {
            $update_data['user_email'] = $saved_email;
        }
        if ($saved_name) {
            $update_data['user_name'] = $saved_name;
        }
        
        if (!empty($update_data)) {
            $update_res = $wpdb->update(
                $table_name,
                $update_data,
                ['session_id' => $session_id],
                array_fill(0, count($update_data), '%s'),
                ['%s']
            );
            //error_log("[DEBUG] mxchat_save_chat_message -> Attempted DB user_email/user_name update for session_id {$session_id}. update_res: {$update_res}");
        }
    }
    
    // 7) Save to session history in wp_options
    $history_key = "mxchat_history_{$session_id}";
    $history = get_option($history_key, []);
    $history[] = [
        'id' => $message_id,
        'role' => $role,
        'content' => $message,
        'timestamp' => round(microtime(true) * 1000),
        'agent_name' => $displayed_name,
    ];
    update_option($history_key, $history, 'no');
    //error_log("[DEBUG] mxchat_save_chat_message -> Updated session history in option: {$history_key}");
    
    // 8) Save the message to DB (INSERT)
    $insert_data = [
        'user_id'        => $user_id,
        'user_identifier'=> $user_identifier,
        'user_email'     => $saved_email ?: $user_email,
        'user_name'      => $saved_name ?: '', //   Add name to insert data
        'session_id'     => $session_id,
        'role'           => $role,
        'message'        => $message,
        'timestamp'      => current_time('mysql', 1),
    ];
    
    // IMPROVED: Handle originating page data
    $columns_exist = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'originating_page_url'");
    
    if ($columns_exist) {
        if ($is_new_session && $role === 'user') {
            // For the first user message, set originating page data
            
            // First check if we have it from the parameter
            if ($originating_page && !empty($originating_page['url'])) {
                $insert_data['originating_page_url'] = $originating_page['url'];
                $insert_data['originating_page_title'] = $originating_page['title'] ?? '';
                
                //error_log("[DEBUG] Setting originating page from parameter: " . $originating_page['url']);
            }
            // Otherwise check if it's stored in the instance property
            else if (isset($this->pending_originating_page) && !empty($this->pending_originating_page['url'])) {
                $insert_data['originating_page_url'] = $this->pending_originating_page['url'];
                $insert_data['originating_page_title'] = $this->pending_originating_page['title'] ?? '';
                
                //error_log("[DEBUG] Setting originating page from pending_originating_page: " . $this->pending_originating_page['url']);
                
                // Clear after using
                unset($this->pending_originating_page);
            }
            // Fallback to HTTP_REFERER if nothing else is available
            else if (isset($_SERVER['HTTP_REFERER'])) {
                $referer_url = esc_url_raw($_SERVER['HTTP_REFERER']);
                $insert_data['originating_page_url'] = $referer_url;
                
                // Generate title from URL
                $parsed_url = parse_url($referer_url);
                $path = isset($parsed_url['path']) ? trim($parsed_url['path'], '/') : '';
                
                if (empty($path) || $path === 'index.php' || $path === 'index.html') {
                    $insert_data['originating_page_title'] = 'Homepage';
                } else {
                    $title = str_replace(['-', '_', '/', '.php', '.html'], ' ', $path);
                    $insert_data['originating_page_title'] = ucwords(trim($title));
                }
                
                //error_log("[DEBUG] Setting originating page from HTTP_REFERER: " . $referer_url);
            }
            
            // Store for this session so all messages have the same originating page
            if (!empty($insert_data['originating_page_url'])) {
                update_option("mxchat_originating_page_{$session_id}", [
                    'url' => $insert_data['originating_page_url'],
                    'title' => $insert_data['originating_page_title']
                ], 'no');
            }
        } else {
            // For subsequent messages in the session, use the stored originating page
            $stored_originating = get_option("mxchat_originating_page_{$session_id}");
            if ($stored_originating && !empty($stored_originating['url'])) {
                $insert_data['originating_page_url'] = $stored_originating['url'];
                $insert_data['originating_page_title'] = $stored_originating['title'] ?? '';
            }
        }
    }

    // Add RAG context if provided (for bot messages)
    if ($rag_context !== null && $role === 'bot') {
        $rag_context_column_exists = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'rag_context'");
        if ($rag_context_column_exists) {
            $insert_data['rag_context'] = is_array($rag_context) ? wp_json_encode($rag_context) : $rag_context;
        }
    }

    $wpdb->insert($table_name, $insert_data);
    //error_log("[DEBUG] mxchat_save_chat_message -> Inserted message into DB. row_id: {$wpdb->insert_id}, data: " . print_r($insert_data, true));
    
    // 9) Send notification email if this is the first user message in a new session
    if ($wpdb->insert_id && $is_new_session && $role === 'user') {
        $this->send_new_chat_notification($session_id, array(
            'identifier' => $user_identifier,
            'email' => $saved_email ?: $user_email,
            'ip' => $_SERVER['REMOTE_ADDR']
        ));
    }
    
    // 10) Schedule delayed transcript email if enabled and message is from user
    if ($wpdb->insert_id && $role === 'user') {
        $this->schedule_delayed_transcript_email($session_id);
    }
    
    //error_log("[DEBUG] mxchat_save_chat_message -> END for session_id: {$session_id}");
    return $message_id;
}

private function send_new_chat_notification($session_id, $user_info = array()) {
    $options = get_option('mxchat_transcripts_options');
    
    // Check if notifications are enabled
    if (empty($options['mxchat_enable_notifications'])) {
        return false;
    }
    
    // Get notification email
    $to = !empty($options['mxchat_notification_email']) ? 
          $options['mxchat_notification_email'] : 
          get_option('admin_email');
    
    if (!is_email($to)) {
        return false;
    }
    
    // Prepare email content
    $subject = sprintf('[%s] New Chat Session Started', get_bloginfo('name'));
    
    $user_identifier = isset($user_info['identifier']) ? $user_info['identifier'] : 'Guest';
    $user_email = isset($user_info['email']) ? $user_info['email'] : 'Not provided';
    $user_ip = isset($user_info['ip']) ? $user_info['ip'] : $_SERVER['REMOTE_ADDR'];
    
    $message = sprintf(
        "A new chat session has started on your website.\n\n" .
        "Session ID: %s\n" .
        "User: %s\n" .
        "Email: %s\n" .
        "IP Address: %s\n" .
        "Time: %s\n\n" .
        "View transcripts: %s",
        $session_id,
        $user_identifier,
        $user_email,
        $user_ip,
        current_time('mysql'),
        admin_url('admin.php?page=mxchat-transcripts')
    );
    
    // Send email
    return wp_mail($to, $subject, $message);
}

/**
 * Schedule delayed transcript email for a session
 * Reschedules if a new user message is received
 */
private function schedule_delayed_transcript_email($session_id) {
    $options = get_option('mxchat_transcripts_options');
    
    // Check if auto-email is enabled
    if (empty($options['mxchat_auto_email_transcript_enabled'])) {
        return;
    }
    
    // Get notification email
    $email = !empty($options['mxchat_notification_email']) ? 
             $options['mxchat_notification_email'] : 
             get_option('admin_email');
    
    if (!is_email($email)) {
        return;
    }
    
    // Get delay in minutes (default 30)
    $delay_minutes = isset($options['mxchat_auto_email_transcript_delay']) ? 
                     intval($options['mxchat_auto_email_transcript_delay']) : 30;
    
    // Clear any existing scheduled event for this session
    $hook = 'mxchat_send_delayed_transcript';
    $args = array($session_id);
    $timestamp = wp_next_scheduled($hook, $args);
    
    if ($timestamp) {
        wp_unschedule_event($timestamp, $hook, $args);
    }
    
    // Schedule new event
    $schedule_time = time() + ($delay_minutes * 60);
    wp_schedule_single_event($schedule_time, $hook, $args);
}

/**
 * Check if chat messages contain contact information (email or phone number)
 *
 * @param array $messages Array of message objects with 'message' property
 * @param object|null $session_data Session data object with user_email property
 * @return bool True if contact info found, false otherwise
 */
private function chat_contains_contact_info($messages, $session_data = null) {
    // Check if session already has a stored email
    if ($session_data && !empty($session_data->user_email)) {
        return true;
    }

    // Email regex pattern
    $email_pattern = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/';

    // Phone number patterns (covers various formats including international, WhatsApp style)
    // Matches: +1234567890, (123) 456-7890, 123-456-7890, 123.456.7890, 1234567890, +1 234 567 8900, etc.
    $phone_pattern = '/(?:\+?\d{1,3}[-.\s]?)?\(?\d{2,4}\)?[-.\s]?\d{2,4}[-.\s]?\d{2,4}(?:[-.\s]?\d{1,4})?/';

    // Only check user messages (not assistant responses)
    foreach ($messages as $msg) {
        if ($msg->role !== 'user') {
            continue;
        }

        $message_text = $msg->message;

        // Check for email
        if (preg_match($email_pattern, $message_text)) {
            return true;
        }

        // Check for phone number (must be at least 7 digits total to avoid false positives)
        if (preg_match($phone_pattern, $message_text, $matches)) {
            // Count actual digits to avoid matching short numbers
            $digits_only = preg_replace('/\D/', '', $matches[0]);
            if (strlen($digits_only) >= 7) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Send the delayed transcript email with .txt attachment
 */
public function mxchat_send_delayed_transcript($session_id) {
    global $wpdb;

    $options = get_option('mxchat_transcripts_options');

    // Get notification email
    $to = !empty($options['mxchat_notification_email']) ?
          $options['mxchat_notification_email'] :
          get_option('admin_email');

    if (!is_email($to)) {
        return false;
    }

    // Get all messages for this session
    $table_name = $wpdb->prefix . 'mxchat_chat_transcripts';
    $messages = $wpdb->get_results($wpdb->prepare(
        "SELECT role, message, timestamp FROM {$table_name}
         WHERE session_id = %s
         ORDER BY timestamp ASC",
        $session_id
    ));

    if (empty($messages)) {
        return false;
    }

    // Get session metadata
    $sessions_table = $wpdb->prefix . 'mxchat_sessions';
    $session_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$sessions_table} WHERE session_id = %s",
        $session_id
    ));

    // Check if contact info is required and if it's present
    $require_contact = !empty($options['mxchat_auto_email_transcript_require_contact']);
    if ($require_contact && !$this->chat_contains_contact_info($messages, $session_data)) {
        // Contact info required but not found - skip sending
        return false;
    }

    // Build transcript content
    $transcript_content = "Chat Transcript\n";
    $transcript_content .= "================\n\n";
    $transcript_content .= "Session ID: " . $session_id . "\n";
    
    if ($session_data) {
        $transcript_content .= "User: " . ($session_data->user_identifier ?: 'Guest') . "\n";
        $transcript_content .= "Email: " . ($session_data->user_email ?: 'Not provided') . "\n";
        $transcript_content .= "Started: " . $session_data->created_at . "\n";
    }
    
    $transcript_content .= "\n" . str_repeat("=", 50) . "\n\n";
    
    // Add messages
    foreach ($messages as $msg) {
        $role_label = ($msg->role === 'user') ? 'User' : 'Assistant';
        $transcript_content .= "[{$msg->timestamp}] {$role_label}:\n";
        $transcript_content .= $msg->message . "\n\n";
    }
    
    // Create temporary file for attachment
    $upload_dir = wp_upload_dir();
    $temp_file = $upload_dir['basedir'] . '/mxchat-transcript-' . $session_id . '.txt';
    file_put_contents($temp_file, $transcript_content);
    
    // Prepare email
    $subject = sprintf('[%s] Chat Transcript - Session %s', get_bloginfo('name'), substr($session_id, 0, 8));
    
    $message = "Please find attached the full chat transcript.\n\n";
    $message .= "Session ID: {$session_id}\n";
    
    if ($session_data) {
        $message .= "User: " . ($session_data->user_identifier ?: 'Guest') . "\n";
        $message .= "Email: " . ($session_data->user_email ?: 'Not provided') . "\n";
    }
    
    $message .= "\nView online: " . admin_url('admin.php?page=mxchat-transcripts');
    
    // Send email with attachment
    $attachments = array($temp_file);
    $result = wp_mail($to, $subject, $message, '', $attachments);
    
    // Clean up temporary file
    if (file_exists($temp_file)) {
        unlink($temp_file);
    }
    
    return $result;
}



public function mxchat_handle_save_email_and_response() {
    //error_log('[DEBUG] ---------- mxchat_handle_save_email_and_response START ----------');
    //error_log('DEBUG: POST data: ' . print_r($_POST, true));

    // Validate nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mxchat_chat_nonce')) {
        //error_log(esc_html__('[ERROR] Invalid nonce in mxchat_handle_save_email_and_response', 'mxchat'));
        wp_send_json_error(['message' => esc_html__('Invalid nonce.', 'mxchat')]);
        wp_die();
    }

    $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
    $email      = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $name       = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : ''; 

    //error_log("[DEBUG] handle_save_email_and_response -> session_id: {$session_id}, email: {$email}, name: {$name}");

    if (empty($session_id) || empty($email)) {
        //error_log("[ERROR] Missing session_id or email: session_id={$session_id}, email={$email}");
        wp_send_json_error(['message' => esc_html__('Session ID or email is missing.', 'mxchat')]);
        wp_die();
    }

    //   Validate name if provided (check if name field is enabled and name is required)
    $options = get_option('mxchat_options', []);
    $name_field_enabled = isset($options['enable_name_field']) && 
        ($options['enable_name_field'] === '1' || $options['enable_name_field'] === 'on');
    
    if ($name_field_enabled && (empty($name) || strlen(trim($name)) < 2 || strlen(trim($name)) > 100)) {
        //error_log("[ERROR] Invalid name: {$name} (enabled: {$name_field_enabled})");
        wp_send_json_error(['message' => esc_html__('Name must be between 2 and 100 characters.', 'mxchat')]);
        wp_die();
    }

    // 1) Always store email in wp_options
    $email_option_key = "mxchat_email_{$session_id}";
    update_option($email_option_key, $email);
    //error_log("[DEBUG] handle_save_email_and_response -> updated option: {$email_option_key} => {$email}");

    //   Store name in wp_options if provided
    if (!empty($name)) {
        $name_option_key = "mxchat_name_{$session_id}";
        update_option($name_option_key, $name);
        //error_log("[DEBUG] handle_save_email_and_response -> updated option: {$name_option_key} => {$name}");
    }

    // 2) (Optional) Also store in DB if a row already exists
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_chat_transcripts';

    // Make sure we have a valid placeholder in prepare
    $sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE session_id = %s", $session_id);
    $session_count = $wpdb->get_var($sql);

    //error_log("[DEBUG] handle_save_email_and_response -> session_count for {$session_id}: {$session_count} (SQL: {$sql})");

    if ($session_count) {
        //   Update both user_email and user_name if row(s) exist
        if (!empty($name)) {
            $update_sql = $wpdb->prepare(
                "UPDATE {$table_name} SET user_email = %s, user_name = %s WHERE session_id = %s",
                $email,
                $name,
                $session_id
            );
        } else {
            $update_sql = $wpdb->prepare(
                "UPDATE {$table_name} SET user_email = %s WHERE session_id = %s",
                $email,
                $session_id
            );
        }
        $wpdb->query($update_sql);
        //error_log("[DEBUG] handle_save_email_and_response -> DB updated: {$update_sql}");
    } else {
        //error_log("[INFO] handle_save_email_and_response -> No DB entry for {$session_id}, so email/name is only in wp_options.");
    }

    // Provide success response (same as original)
    $bot_message = __('Thanks for providing your email! You can continue chatting now.', 'mxchat');
    //error_log("[DEBUG] handle_save_email_and_response -> success, returning bot_message: {$bot_message}");
    wp_send_json_success(['message' => $bot_message]);
    wp_die();
}

public function mxchat_check_email_provided() {
    //error_log('[DEBUG] ---------- mxchat_check_email_provided START ----------');

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mxchat_chat_nonce')) {
        //error_log('[ERROR] Invalid nonce in mxchat_check_email_provided');
        wp_send_json_error(['message' => esc_html__('Invalid nonce', 'mxchat')]);
    }

    $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
    if (empty($session_id)) {
        //error_log('[ERROR] No session ID provided in mxchat_check_email_provided');
        wp_send_json_error(['message' => esc_html__('No session ID provided', 'mxchat')]);
    }

    // Check if the user is logged in
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        //error_log("[DEBUG] User is logged in as {$current_user->user_email}");
        
        //   Get user's display name for logged in users
        $user_name = !empty($current_user->display_name) ? $current_user->display_name : 
                    (!empty($current_user->first_name) ? $current_user->first_name : '');
        
        $response_data = ['logged_in' => true, 'email' => $current_user->user_email];
        if (!empty($user_name)) {
            $response_data['name'] = $user_name;
        }
        
        wp_send_json_success($response_data);
    }

    //   Check if name field is required
    $options = get_option('mxchat_options', []);
    $name_field_enabled = isset($options['enable_name_field']) && 
        ($options['enable_name_field'] === '1' || $options['enable_name_field'] === 'on');

    $email_option_key = "mxchat_email_{$session_id}";
    $stored_email = get_option($email_option_key, '');
    
    //   Check for stored name
    $name_option_key = "mxchat_name_{$session_id}";
    $stored_name = get_option($name_option_key, '');

    //error_log("[DEBUG] mxchat_check_email_provided -> Checking email option: {$email_option_key}, found: {$stored_email}");
    //error_log("[DEBUG] mxchat_check_email_provided -> Checking name option: {$name_option_key}, found: {$stored_name}, required: " . ($name_field_enabled ? 'yes' : 'no'));

    //   Check if we have email and name (if name is required)
    $has_required_info = !empty($stored_email);
    
    if ($name_field_enabled) {
        $has_required_info = $has_required_info && !empty($stored_name);
    }

    if ($has_required_info) {
        //error_log("[DEBUG] mxchat_check_email_provided -> Required info found, returning success");
        
        $response_data = ['email' => $stored_email];
        if (!empty($stored_name)) {
            $response_data['name'] = $stored_name;
        }
        
        wp_send_json_success($response_data);
    } else {
        //error_log("[DEBUG] mxchat_check_email_provided -> Required info missing, returning error");
        wp_send_json_error(['message' => esc_html__('No email found', 'mxchat')]);
    }
}

/**
 * Send error response in appropriate format based on streaming mode
 * ADDED: Helper method to consistently handle errors in both streaming and non-streaming modes
 *
 * @param string $error_message The error message to display
 * @param string $error_code Optional error code for debugging
 */
private function send_error_response($error_message, $error_code = 'api_error') {
    if ($this->is_streaming) {
        echo "data: " . json_encode([
            'error' => true,
            'error_message' => $error_message,
            'error_code' => $error_code,
            'text' => $error_message,
            'message' => $error_message
        ]) . "\n\n";
        echo "data: [DONE]\n\n";
        flush();
    } else {
        wp_send_json_error([
            'error_message' => $error_message,
            'error_code' => $error_code
        ]);
    }
    wp_die();
}

public function mxchat_handle_chat_request() {
    global $wpdb;

    // Debug: Log incoming bot_id
    $bot_id = isset($_POST['bot_id']) ? sanitize_key($_POST['bot_id']) : 'default';
    error_log("=== MXCHAT DEBUG: Starting chat request ===");
    error_log("MXCHAT DEBUG: Bot ID received: " . $bot_id);
    
    //   Get bot-specific options
    $bot_options = $this->get_bot_options($bot_id);
    $current_options = !empty($bot_options) ? $bot_options : $this->options;

    //   Check if this is a streaming request
    //   Allow force_streaming_test parameter to bypass the setting check (for admin compatibility testing)
    $force_streaming_test = isset($_POST['force_streaming_test']) && $_POST['force_streaming_test'] === '1' && current_user_can('administrator');
    $is_streaming = isset($_POST['action']) && $_POST['action'] === 'mxchat_stream_chat' &&
                   ($force_streaming_test || (isset($current_options['enable_streaming_toggle']) && $current_options['enable_streaming_toggle'] === 'on'));

    // ADDED: Store streaming state in class property for use in private methods
    $this->is_streaming = $is_streaming;

    // NOTE: Streaming headers are now set later via setup_streaming_headers()
    // This allows actions/forms to return JSON responses without header conflicts

    // Check if MX Chat Moderation is active
    if (class_exists('MX_Chat_Moderation')) {
        // Get user email and IP
        $user_email = '';
        $user_ip = $_SERVER['REMOTE_ADDR'];

        // If user is logged in, get their email
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $user_email = $current_user->user_email;
        }

        // Create ban handler instance
        $ban_handler = new MX_Chat_Ban_Handler();

        // Check if user is banned by IP
        if ($ban_handler->check_ban($user_ip, 'ip')) {
            wp_send_json([
                'success' => false,
                'message' => esc_html__('Access denied. Your IP address has been banned.', 'mxchat'),
                'status' => 'banned'
            ]);
            wp_die();
        }

        // If user is logged in, also check email
        if (!empty($user_email) && $ban_handler->check_ban($user_email, 'email')) {
            wp_send_json([
                'success' => false,
                'message' => esc_html__('Access denied. Your email address has been banned.', 'mxchat'),
                'status' => 'banned'
            ]);
            wp_die();
        }
    }

    $this->fallbackResponse = ['text' => '', 'html' => '', 'images' => []];
    $this->productCardHtml = '';

    // Get the actual WordPress user ID if logged in
    $is_logged_in = is_user_logged_in();
    if ($is_logged_in) {
        $user_id = get_current_user_id(); // This will get the actual WordPress user ID
    } else {
        // For logged-out users, use your existing identifier method
        $user_id = $this->mxchat_get_user_identifier();
    }

    // Get and sanitize the user identifier
    $user_id = sanitize_key($user_id);

    // Check rate limit using new settings structure
    $rate_limit_result = $this->check_rate_limit();

    if ($rate_limit_result !== true) {
        wp_send_json([
            'success' => false,
            'message' => $rate_limit_result['message'],
            'status' => 'rate_limit_exceeded'
        ]);
        wp_die();
    }

    // Rest of your existing code...
    $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';

    if (empty($session_id)) {
        wp_send_json_error(esc_html__('Session ID is missing.', 'mxchat'));
        wp_die();
    }

    // SECURITY FIX: Verify session ownership before processing chat request
    // If IP/user changed, signal frontend to reset session instead of blocking
    $current_user_identifier = MxChat_User::mxchat_get_user_identifier();
    $session_owner = get_option("mxchat_session_owner_{$session_id}");

    if ($session_owner && $session_owner !== $current_user_identifier) {
        // Instead of blocking, tell frontend to start a fresh session
        wp_send_json_error([
            'message' => esc_html__('Your session has expired. Starting a new conversation.', 'mxchat'),
            'code' => 'session_expired',
            'action' => 'reset_session'
        ]);
        wp_die();
    }

    // Validate and sanitize the incoming message
    if (empty($_POST['message'])) {
        wp_send_json_error(esc_html__('No message received.', 'mxchat'));
        wp_die();
    }
    
    
    //   Track originating page for first message in session
    $table_name = $wpdb->prefix . 'mxchat_chat_transcripts';

    // Check if originating page columns exist
    $columns_exist = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'originating_page_url'");

    if ($columns_exist) {
        // Check if this session already has messages
        $message_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE session_id = %s",
            $session_id
        ));
        
        // If this is the first message in the session
        if ($message_count == 0) {
            // Get originating page from JavaScript (preferred) or HTTP_REFERER (fallback)
            $originating_url = '';
            $originating_title = '';
            
            // Try to get from POST data first (sent by JavaScript)
            if (isset($_POST['current_page_url'])) {
                $originating_url = esc_url_raw($_POST['current_page_url']);
                $originating_title = isset($_POST['current_page_title']) 
                    ? sanitize_text_field($_POST['current_page_title']) 
                    : '';
            }
            // Fallback to HTTP_REFERER if not provided by JavaScript
            else if (isset($_SERVER['HTTP_REFERER'])) {
                $originating_url = esc_url_raw($_SERVER['HTTP_REFERER']);
            }
            
            // Generate title if we have URL but no title
            if ($originating_url && empty($originating_title)) {
                $parsed_url = parse_url($originating_url);
                $path = isset($parsed_url['path']) ? trim($parsed_url['path'], '/') : '';
                
                if (empty($path) || $path === 'index.php' || $path === 'index.html') {
                    $originating_title = 'Homepage';
                } else {
                    // Clean up the path to make a readable title
                    $originating_title = str_replace(['-', '_', '/', '.php', '.html'], ' ', $path);
                    $originating_title = ucwords(trim($originating_title));
                }
            }
            
            // Store for later use when saving the message
            $this->pending_originating_page = [
                'url' => $originating_url,
                'title' => $originating_title
            ];
        }
    }
        
        

        //   Get page context if provided
        $page_context = null;
        if (isset($_POST['page_context']) && !empty($_POST['page_context'])) {
            $page_context_raw = stripslashes($_POST['page_context']);
            $page_context = json_decode($page_context_raw, true);
            
            // Validate page context structure
            if (is_array($page_context) && 
                isset($page_context['url']) && 
                isset($page_context['title']) && 
                isset($page_context['content'])) {
                
                // Sanitize page context
                $page_context['url'] = esc_url_raw($page_context['url']);
                $page_context['title'] = sanitize_text_field($page_context['title']);
                $page_context['content'] = wp_kses_post($page_context['content']);
            } else {
                $page_context = null;
            }
        }

        // Modify the message sanitization to preserve PHP tags in code blocks
        $allowed_tags = [
            'pre' => [],
            'code' => ['class' => true],
            'span' => ['class' => true],
            'div' => ['class' => true],
        ];

        // First preserve code blocks
        $message = preg_replace_callback('/<pre><code.*?>.*?<\/code><\/pre>/s', function($matches) {
            return htmlspecialchars_decode($matches[0]);
        }, $_POST['message']);

        // Then apply sanitization
        $message = wp_kses($message, $allowed_tags);

        // Preserve code blocks from markdown conversion
        $message = preg_replace('/```(\w+)?\s*([\s\S]+?)```/s', '<pre><code class="$1">$2</code></pre>', $message);
        $message = apply_filters('mxchat_filter_message', $message, 'prompt', $session_id);

    // ===== SIMPLIFIED TESTING PANEL INITIALIZATION =====
        // Always initialize testing data for admins (no toggle needed)
        $testing_data = null;
        if (current_user_can('administrator')) {
            // For vision messages, use the original user message for the query display
            $query_for_testing = $message;
            if (isset($_POST['vision_processed']) && $_POST['vision_processed'] && isset($_POST['original_user_message'])) {
                $query_for_testing = sanitize_textarea_field($_POST['original_user_message']);
            }
            
            $testing_data = [
                'query' => $query_for_testing,
                'timestamp' => time(),
                'top_matches' => [],
                'action_matches' => [], //   Initialize action matches array
                'page_context' => $page_context, //   Include page context in testing data
                'is_vision' => isset($_POST['vision_processed']) && $_POST['vision_processed'],
                'bot_id' => $bot_id //   Include bot ID in testing data
            ];
            
            // Get similarity threshold from bot options or default options
            $similarity_threshold = isset($current_options['similarity_threshold']) 
                ? ((int) $current_options['similarity_threshold']) / 100 
                : 0.35;
            
            $testing_data['similarity_threshold'] = $similarity_threshold;
            
            // Determine knowledge base type using bot-specific config
            $bot_pinecone_config = $this->get_bot_pinecone_config($bot_id);
            $use_pinecone = isset($bot_pinecone_config['use_pinecone']) ? $bot_pinecone_config['use_pinecone'] : false;
            $testing_data['knowledge_base_type'] = $use_pinecone ? 'Pinecone' : 'WordPress Database';
        }
        // ===== END SIMPLIFIED TESTING INITIALIZATION =====

    // Add debug before and after:
    //error_log('MxChat Core: About to call mxchat_pre_process_message filter with message: ' . $message);
    $pre_processed_result = apply_filters('mxchat_pre_process_message', $message, $user_id, $session_id);
    //error_log('MxChat Core: Filter returned: ' . (is_array($pre_processed_result) ? 'array' : $pre_processed_result));


        // If the pre-processing returned a result (not the original message), use it directly
        if (is_array($pre_processed_result) && isset($pre_processed_result['text'])) {
            // Save the AI response
            $this->mxchat_save_chat_message($session_id, 'bot', $pre_processed_result['text']);
            
            // Save HTML content if provided
            if (!empty($pre_processed_result['html'])) {
                $this->mxchat_save_chat_message($session_id, 'bot', $pre_processed_result['html']);
            }
            
            // Add testing data if admin
            $response_data = [
                'text' => $pre_processed_result['text'],
                'html' => $pre_processed_result['html'] ?? '',
                'session_id' => $session_id
            ];
            
            if ($testing_data !== null) {
                $response_data['testing_data'] = $testing_data;
            }
            
            wp_send_json($response_data);
            wp_die();
        }

        // Save the user's message - handle vision processed messages differently
        if (isset($_POST['vision_processed']) && $_POST['vision_processed'] && isset($_POST['original_user_message'])) {
            // For vision messages, save the original user message with image indicator
            $original_message = sanitize_textarea_field($_POST['original_user_message']);
            if (isset($_POST['vision_images_count']) && $_POST['vision_images_count'] > 0) {
                $image_count = intval($_POST['vision_images_count']);
                $original_message .= " [{$image_count} image(s)]";
            }
            $this->mxchat_save_chat_message($session_id, 'user', $original_message);
        } else {
            // Regular message - save as normal
            $this->mxchat_save_chat_message($session_id, 'user', $message);
        }

        
    if (is_email($message)) {
            // Add the email to Loops
            $this->add_email_to_loops($message);
            
            // Get the user's success message instruction using current_options
            $user_success_message = $current_options['email_capture_response'] ?? __('Thank you for providing your email! You\'ve been added to our list.', 'mxchat');
            
            // Set instruction for AI using the user's success message
            $this->current_action_instruction = $user_success_message;
            
            // Clear the email capture transient since we got the email
            delete_transient('mxchat_email_capture_' . $user_id);
        }
        
        //   Check if we're in an email capture flow but user hasn't provided email yet
        elseif (get_transient('mxchat_email_capture_' . $user_id)) {
            // Check if the message contains an email (not the whole message being an email)
            if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $message, $matches)) {
                $extracted_email = $matches[0];
                
                // Add the extracted email to Loops
                $this->add_email_to_loops($extracted_email);
                
                // Get the user's success message instruction using current_options
                $user_success_message = $current_options['email_capture_response'] ?? __('Thank you for providing your email! You\'ve been added to our list.', 'mxchat');
                
                // Set instruction for AI using the user's success message
                $this->current_action_instruction = $user_success_message;
                
                // Clear the email capture transient since we got the email
                delete_transient('mxchat_email_capture_' . $user_id);
            }
            // If no email found but we're in capture mode, remind them
            else {
                // Get the original instruction to remind them using current_options
                $original_instruction = $current_options['triggered_phrase_response'] ?? __("Please provide your email address.", 'mxchat');
                $this->current_action_instruction = $original_instruction;
            }
        }

        $intent_info = '';

        // Check chat mode
        $chat_mode = get_option("mxchat_mode_{$session_id}", 'ai');

        // Handle agent mode
    // Handle agent mode
        if ($chat_mode === 'agent') {
            // First, check for switch intent before doing anything else
            $intent_matched = $this->mxchat_check_intent_and_invoke_callback($message, $user_id, $session_id);

            //   Capture action analysis for testing panel after intent check
            if ($testing_data !== null && isset($this->last_action_analysis) && !empty($this->last_action_analysis)) {
                $testing_data['action_matches'] = $this->last_action_analysis;
            }
            
            // Around line 506, in the agent mode handling section:
            if ($intent_matched && !empty($this->fallbackResponse['text'])) {
                // Update chat mode first
                update_option("mxchat_mode_{$session_id}", 'ai');
            
                // Clear any existing PDF context to start fresh
                $this->clear_pdf_transients($session_id);
            
                // Prepare clean switch response with explicit chat_mode
                $response_data = [
                    'text' => $this->fallbackResponse['text'],
                    'html' => $this->fallbackResponse['html'] ?? '',
                    'session_id' => $session_id,
                    'chat_mode' => 'ai' // EXPLICITLY SET THIS
                ];
            
                if ($testing_data !== null) {
                    $response_data['testing_data'] = $testing_data;
                }
            
                // Save the mode switch message
                $this->mxchat_save_chat_message($session_id, 'system', esc_html__('Switched to AI chat mode', 'mxchat'));
                $this->mxchat_save_chat_message($session_id, 'bot', $this->fallbackResponse['text']);
            
                // Send response and exit
                wp_send_json($response_data);
                wp_die();
            } elseif (!$intent_matched) {
                // No intent matched, handle live agent message
                try {
                    $this->mxchat_send_user_message_to_agent($message, $user_id, $session_id);

                    $agent_response = [
                        'status' => 'waiting_for_agent',
                        'message' => esc_html__('Message sent to live agent.', 'mxchat')
                    ];
                    
                    if ($testing_data !== null) {
                        $agent_response['testing_data'] = $testing_data;
                    }

                    wp_send_json_success($agent_response);
                } catch (\Exception $e) {
                    wp_send_json_error(esc_html__('Failed to send message to agent', 'mxchat'));
                }
                wp_die();
            }
        }

        // Step 1: Check for new PDF URL in the message
        if (!isset($_POST['vision_processed']) && preg_match('/https?:\/\/[^\s"]+/i', $message, $matches)) {
            $new_pdf_url = $matches[0];

            // Check if this is likely a PDF-related request
            $pdf_keywords = ['pdf', 'document', 'read', 'analyze'];
            $is_pdf_request = false;

            foreach ($pdf_keywords as $keyword) {
                if (stripos($message, $keyword) !== false) {
                    $is_pdf_request = true;
                    break;
                }
            }

            // If it looks like a PDF request or we're waiting for a PDF URL
            if ($is_pdf_request || get_transient('mxchat_waiting_for_pdf_url_' . $session_id)) {
                // Validate HTTPS
                if (wp_http_validate_url($new_pdf_url) && parse_url($new_pdf_url, PHP_URL_SCHEME) === 'https') {
                    // Extract filename from URL
                    $pdf_filename = basename(parse_url($new_pdf_url, PHP_URL_PATH));

                    // Clear previous PDF transients
                    $this->clear_pdf_transients($session_id);

                    // Process new PDF using current_options
                    $max_pages = $current_options['pdf_max_pages'] ?? 69;
                    $embeddings = $this->fetch_and_split_pdf_pages($new_pdf_url, $max_pages);

                    if ($embeddings === 'too_many_pages') {
                        $error_text = sprintf(
                            $current_options['pdf_intent_error_text'] ??
                            esc_html__("The provided PDF exceeds the maximum allowed limit of %d pages. Please provide a smaller document.", 'mxchat'),
                            $max_pages
                        );
                        $this->fallbackResponse['text'] = $error_text;
                    } elseif ($embeddings) {
                        // Store new PDF information
                        $pdf_filename = basename(parse_url($new_pdf_url, PHP_URL_PATH));

                        // If the filename is generic, create a more descriptive one
                        if (in_array($pdf_filename, ['results_download.php', 'download.php', 'view.php', 'pdf.php']) ||
                            strpos($pdf_filename, '.php') !== false) {
                            $pdf_filename = 'Document_' . date('Y-m-d_H-i') . '.pdf';
                        }

                        set_transient('mxchat_pdf_url_' . $session_id, $new_pdf_url, HOUR_IN_SECONDS);
                        set_transient('mxchat_pdf_filename_' . $session_id, $pdf_filename, HOUR_IN_SECONDS);
                        set_transient('mxchat_pdf_embeddings_' . $session_id, $embeddings, HOUR_IN_SECONDS);
                        set_transient('mxchat_include_pdf_in_context_' . $session_id, true, HOUR_IN_SECONDS);

                        $success_text = $current_options['pdf_intent_success_text'] ??
                            esc_html__("I've processed the new PDF '{$pdf_filename}'. What questions do you have about it?", 'mxchat');

                        $pdf_response = [
                            'success' => true,
                            'message' => $success_text,
                            'data' => [
                                'filename' => $pdf_filename
                            ]
                        ];
                        
                        if ($testing_data !== null) {
                            $pdf_response['testing_data'] = $testing_data;
                        }

                        wp_send_json($pdf_response);
                        wp_die();
                    } else {
                        $error_text = $current_options['pdf_intent_error_text'] ??
                            esc_html__("Sorry, I couldn't process the PDF. Please ensure it's a valid file.", 'mxchat');
                        $this->fallbackResponse['text'] = $error_text;
                    }

                    $pdf_error_response = [
                        'success' => false,
                        'message' => $this->fallbackResponse['text']
                    ];
                    
                    if ($testing_data !== null) {
                        $pdf_error_response['testing_data'] = $testing_data;
                    }

                    wp_send_json($pdf_error_response);
                    wp_die();
                }
            }
        }


        // Step 2: Detect intent and handle intent-based responses
        $intent_result = $this->mxchat_check_intent_and_invoke_callback($message, $user_id, $session_id);

        //   Capture action analysis for testing panel after intent check
        if ($testing_data !== null && isset($this->last_action_analysis) && !empty($this->last_action_analysis)) {
            $testing_data['action_matches'] = $this->last_action_analysis;
        }

        // Step 3: Handle the intent result appropriately
        if ($intent_result !== false) {
            // Intent was matched - ALWAYS send as JSON response, never streaming
            
            if (is_array($intent_result) && (isset($intent_result['text']) || isset($intent_result['html']))) {
                // Intent returned a direct response array
                $response_data = [
                    'text' => $intent_result['text'] ?? '',
                    'html' => $intent_result['html'] ?? '',
                    'session_id' => $session_id
                ];

                // IMPORTANT: Include chat_mode if present (for WhatsApp, Slack, etc.)
                if (isset($intent_result['chat_mode'])) {
                    $response_data['chat_mode'] = $intent_result['chat_mode'];
                }

                if ($testing_data !== null) {
                    $response_data['testing_data'] = $testing_data;
                }

                wp_send_json($response_data);
                wp_die();
            } else if ($intent_result === true && (!empty($this->fallbackResponse['text']) || !empty($this->fallbackResponse['html']))) {
                // Intent returned true and set fallbackResponse

                // SAVE TO TRANSCRIPT FIRST
                if (!empty($this->fallbackResponse['text'])) {
                    $this->mxchat_save_chat_message($session_id, 'bot', $this->fallbackResponse['text']);
                }
                if (!empty($this->fallbackResponse['html'])) {
                    $this->mxchat_save_chat_message($session_id, 'bot', $this->fallbackResponse['html']);
                }

                $response_data = [
                    'text' => $this->fallbackResponse['text'] ?? '',
                    'html' => $this->fallbackResponse['html'] ?? '',
                    'session_id' => $session_id
                ];

                if (isset($this->fallbackResponse['chat_mode'])) {
                    $response_data['chat_mode'] = $this->fallbackResponse['chat_mode'];
                }

                if ($testing_data !== null) {
                    $response_data['testing_data'] = $testing_data;
                }

                wp_send_json($response_data);
                wp_die();
            }
        }

        // If we get here, no intent matched OR the intent didn't provide a usable response

        // Step 4: Generate AI response
        // Get session start timestamp - when persistence is OFF, only include messages from this page load
        $session_start_timestamp = isset($_POST['session_start_timestamp']) ? intval($_POST['session_start_timestamp']) : 0;
        $conversation_history = $this->mxchat_fetch_conversation_history_for_ai($session_id, $session_start_timestamp);
        $this->mxchat_increment_chat_count();
        
        // Generate embedding for the user's query - USE BOT-SPECIFIC API KEY
        $api_key = $current_options['api_key'] ?? $this->options['api_key'];
        $user_message_embedding = $this->mxchat_generate_embedding($message, $api_key);
        
        // Check if the embedding generation returned an error
        if (is_array($user_message_embedding) && isset($user_message_embedding['error'])) {
            $error_message = $user_message_embedding['error'];
            $error_code = $user_message_embedding['error_code'] ?? 'embedding_error';

            // FIXED: Send error in appropriate format based on streaming mode
            if ($is_streaming) {
                echo "data: " . json_encode([
                    'error' => true,
                    'error_message' => $error_message,
                    'error_code' => $error_code,
                    'text' => $error_message,
                    'message' => $error_message
                ]) . "\n\n";
                echo "data: [DONE]\n\n";
                flush();
            } else {
                wp_send_json_error([
                    'error_message' => $error_message,
                    'error_code' => $error_code
                ]);
            }
            wp_die();
        }

        // Check if the embedding is valid
        if (!is_array($user_message_embedding) || empty($user_message_embedding)) {
            $error_message = esc_html__('Unable to process your message. The embedding service is not responding correctly.', 'mxchat');

            // FIXED: Send error in appropriate format based on streaming mode
            if ($is_streaming) {
                echo "data: " . json_encode([
                    'error' => true,
                    'error_message' => $error_message,
                    'error_code' => 'invalid_embedding',
                    'text' => $error_message,
                    'message' => $error_message
                ]) . "\n\n";
                echo "data: [DONE]\n\n";
                flush();
            } else {
                wp_send_json_error([
                    'error_message' => $error_message,
                    'error_code' => 'invalid_embedding'
                ]);
            }
            wp_die();
        }

        // Build context with both knowledge base and PDF content if available
        $context_content = "User asked: '{$message}'\n\n";
        
        //   Add action instruction if present (add this right after the above line)
        if (!empty($this->current_action_instruction)) {
            $context_content .= "===== SPECIAL INSTRUCTION =====\n";
            $context_content .= "IMPORTANT: " . $this->current_action_instruction . "\n";
            $context_content .= "Respond naturally and conversationally while following this instruction.\n";
            $context_content .= "===== END SPECIAL INSTRUCTION =====\n\n";
            
            // Clear the instruction after using it
            $this->current_action_instruction = null;
        }


        //   Add page context if available and contextual awareness is enabled using current_options
        if ($page_context && isset($current_options['contextual_awareness_toggle']) && $current_options['contextual_awareness_toggle'] === 'on') {
            $context_content .= "===== CURRENT PAGE CONTEXT =====\n";
            $context_content .= "Page URL: " . $page_context['url'] . "\n";
            $context_content .= "Page Title: " . $page_context['title'] . "\n";
            $context_content .= "Page Content: " . $page_context['content'] . "\n";
            $context_content .= "===== END CURRENT PAGE CONTEXT =====\n\n";
        }

        // Get relevant content from knowledge base - PASS BOT_ID and MESSAGE for Vector Store
        $relevant_content = $this->mxchat_find_relevant_content($user_message_embedding, $bot_id, $message);
        
        // NEW: Also extract URLs from system instructions (only if citation links enabled)
        // Use fresh options to ensure we get the latest setting value
        $fresh_options = get_option('mxchat_options', []);
        $citation_links_enabled = isset($fresh_options['citation_links_toggle']) ? ($fresh_options['citation_links_toggle'] === 'on') : true;

        $system_instructions = $this->get_system_instructions($bot_id, $session_id);
        if ($citation_links_enabled && !empty($system_instructions)) {
            preg_match_all(
                '#\bhttps?://[^\s<>"\']+#i',
                $system_instructions,
                $system_instruction_urls
            );

            if (!empty($system_instruction_urls[0])) {
                // Merge with existing valid URLs
                $this->current_valid_urls = array_merge(
                    $this->current_valid_urls,
                    $system_instruction_urls[0]
                );
                // Remove duplicates
                $this->current_valid_urls = array_unique($this->current_valid_urls);

                error_log("Added " . count($system_instruction_urls[0]) . " URLs from system instructions");
            }
        }
        
// ===== CAPTURE REAL SIMILARITY DATA FOR ADMINS =====
if ($testing_data !== null && $this->last_similarity_analysis !== null) {
    // Update testing data with the REAL similarity analysis
    $testing_data['top_matches'] = $this->last_similarity_analysis['top_matches'];
    $testing_data['total_documents_checked'] = $this->last_similarity_analysis['total_checked'] ?? 0;
    $testing_data['knowledge_base_type'] = $this->last_similarity_analysis['knowledge_base_type'];
}
// ===== END SIMILARITY DATA CAPTURE =====

// NEW: Add valid URLs to testing data for admin panel display (AFTER similarity data)
if ($testing_data !== null && !empty($this->current_valid_urls)) {
    $testing_data['approved_urls'] = array_values($this->current_valid_urls);
    error_log("Added " . count($this->current_valid_urls) . " approved URLs to testing data");
}
        
        if (!empty($relevant_content)) {
            $context_content .= "===== OFFICIAL KNOWLEDGE DATABASE CONTENT =====\n" . $relevant_content . "\n===== END OF OFFICIAL KNOWLEDGE DATABASE CONTENT =====\n\n";
        } else {
            $context_content .= "===== NO RELEVANT CONTENT FOUND IN KNOWLEDGE DATABASE =====\n";
        }
        
        // NEW: Add approved URLs list to context for AI (only if citation links enabled)
        if ($citation_links_enabled && !empty($this->current_valid_urls)) {
            $context_content .= "===== APPROVED URLS FOR CITATIONS =====\n";
            $context_content .= "You may ONLY use these exact URLs in your response:\n";
            foreach ($this->current_valid_urls as $url) {
                $context_content .= "- " . $url . "\n";
            }
            $context_content .= "\nCRITICAL: Do NOT create, modify, extend, or invent any other URLs. ";
            $context_content .= "===== END APPROVED URLS =====\n\n";
        }
        
        // Check for and include PDF content
        $pdf_url = get_transient('mxchat_pdf_url_' . $session_id);
        $pdf_embeddings = get_transient('mxchat_pdf_embeddings_' . $session_id);
        $pdf_filename = get_transient('mxchat_pdf_filename_' . $session_id);
        if ($pdf_url && $pdf_embeddings && get_transient('mxchat_include_pdf_in_context_' . $session_id)) {
            $relevant_pdf_pages = $this->find_relevant_pdf_pages($user_message_embedding, $pdf_embeddings);
            if (!empty($relevant_pdf_pages)) {
                $context_content .= "Relevant content from PDF document '{$pdf_filename}':\n";
                foreach ($relevant_pdf_pages as $page_data) {
                    $context_content .= "Page {$page_data['page_number']} of '{$pdf_filename}': {$page_data['text']}\n";
                }
                $context_content .= "\n";
            }
        }

        // Check for and include Word content
        $word_url = get_transient('mxchat_word_url_' . $session_id);
        $word_embeddings = get_transient('mxchat_word_embeddings_' . $session_id);
        $word_filename = get_transient('mxchat_word_filename_' . $session_id);
        if ($word_url && $word_embeddings && get_transient('mxchat_include_word_in_context_' . $session_id)) {
            $relevant_word_chunks = $this->word_handler->mxchat_find_relevant_word_chunks($user_message_embedding, $word_embeddings);
            if (!empty($relevant_word_chunks)) {
                $context_content .= "Relevant content from Word document '{$word_filename}':\n";
                foreach ($relevant_word_chunks as $chunk_data) {
                    $context_content .= "Section {$chunk_data['chunk_number']} of '{$word_filename}': {$chunk_data['text']}\n";
                }
                $context_content .= "\n";
            }
        }
        
        $context_content = apply_filters('mxchat_prepare_context', $context_content, $session_id);

        // Extract model from current options for bot-specific model support
        $selected_model = isset($current_options['model']) ? $current_options['model'] : 'gpt-5.1-chat-latest';
        
        $response = $this->mxchat_generate_response(
            $context_content,
            $current_options['api_key'] ?? $this->options['api_key'],
            $current_options['xai_api_key'] ?? $this->options['xai_api_key'],
            $current_options['claude_api_key'] ?? $this->options['claude_api_key'],
            $current_options['deepseek_api_key'] ?? $this->options['deepseek_api_key'],
            $current_options['gemini_api_key'] ?? $this->options['gemini_api_key'],
            $current_options['openrouter_api_key'] ?? $this->options['openrouter_api_key'],
            $conversation_history,
            $is_streaming,
            $session_id,
            $testing_data,
            $selected_model
        );
        
        // Handle streaming vs non-streaming responses
        if ($is_streaming) {
            // Check if streaming actually happened or if it fell back to regular response
            if ($response === true) {
                wp_die();
            }
            // If we get here, streaming fell back to regular response, continue
            // But if there's an error, we need to send it as SSE format since headers are already set
            if (is_array($response) && isset($response['error'])) {
                $error_message = $response['error'];
                $error_code = $response['error_code'] ?? 'api_error';
                // Send error in SSE format that the client JS can handle
                echo "data: " . json_encode([
                    'error' => true,
                    'error_message' => $error_message,
                    'error_code' => $error_code,
                    'text' => $error_message, // Also include as text for fallback handling
                    'message' => $error_message
                ]) . "\n\n";
                echo "data: [DONE]\n\n";
                flush();
                wp_die();
            }
        }

        // Check if the response is an error array (non-streaming mode)
        if (is_array($response) && isset($response['error'])) {
            wp_send_json_error([
                'error_message' => $response['error'],
                'error_code' => $response['error_code'] ?? 'api_error'
            ]);
            wp_die();
        }
        
        // DEBUG: Check what we have
        error_log("=== BEFORE URL VALIDATION ===");
        error_log("current_valid_urls is empty? " . (empty($this->current_valid_urls) ? 'YES' : 'NO'));
        error_log("current_valid_urls count: " . count($this->current_valid_urls));
        error_log("current_valid_urls content: " . print_r($this->current_valid_urls, true));
        
        // If we get here, the response is valid text - now validate URLs
        if (!empty($this->current_valid_urls)) {
            error_log("CALLING validate_and_clean_urls");
            $response = $this->validate_and_clean_urls($response, $this->current_valid_urls);
        } else {
            error_log("SKIPPING validation - current_valid_urls is empty");
        }
        // ===== END URL VALIDATION =====

        // Prepare RAG context data for storage (only include documents used for context)
        $rag_context_for_storage = null;
        $has_rag_data = $this->last_similarity_analysis !== null && !empty($this->last_similarity_analysis['top_matches']);
        $has_action_data = isset($this->last_action_analysis) && !empty($this->last_action_analysis);

        if ($has_rag_data || $has_action_data) {
            $rag_context_for_storage = [];

            // Add RAG/source data if available
            if ($has_rag_data) {
                $rag_context_for_storage['top_matches'] = $this->last_similarity_analysis['top_matches'];
                $rag_context_for_storage['approved_urls'] = $this->current_valid_urls ?? [];
                $rag_context_for_storage['similarity_threshold'] = $this->last_similarity_analysis['threshold_used'] ?? 0.35;
                $rag_context_for_storage['knowledge_base_type'] = $this->last_similarity_analysis['knowledge_base_type'] ?? 'WordPress Database';
                $rag_context_for_storage['total_documents_checked'] = $this->last_similarity_analysis['total_checked'] ?? 0;
            }

            // Add action analysis data if available
            if ($has_action_data) {
                $rag_context_for_storage['action_analysis'] = $this->last_action_analysis;
            }
        }

        // Save the cleaned response with RAG context
        $this->mxchat_save_chat_message($session_id, 'bot', $response, null, $rag_context_for_storage);

        // Step 5: Save additional content if available
        if (!empty($this->productCardHtml)) {
            $this->mxchat_save_chat_message($session_id, 'bot', $this->productCardHtml);
        }

        if (!empty($this->fallbackResponse['html'])) {
            $this->mxchat_save_chat_message($session_id, 'bot', $this->fallbackResponse['html']);
        }

        // Step 6: Return the response
        // DEBUG: Check if newlines exist in the response
        error_log("=== MXCHAT NON-STREAMING RESPONSE DEBUG ===");
        error_log("Response has newlines: " . (strpos($response, "\n") !== false ? 'YES' : 'NO'));
        error_log("Response first 500 chars: " . substr($response, 0, 500));

        $response_data = [
            'text' => $response,
            'html' => !empty($this->productCardHtml) ? $this->productCardHtml : ($this->fallbackResponse['html'] ?? ''),
            'session_id' => $session_id
        ];

        // Always add testing data for admins (no toggle needed)
        if ($testing_data !== null) {
            $response_data['testing_data'] = $testing_data;
        }

        wp_send_json($response_data);
        wp_die();
}

/**
 * Get bot-specific options for multi-bot functionality
 * Falls back to default options if bot_id is 'default' or multi-bot add-on is not active
 */
// Also debug the bot options retrieval
private function get_bot_options($bot_id = 'default') {
    error_log("MXCHAT DEBUG: get_bot_options called for bot: " . $bot_id);
    
    if ($bot_id === 'default' || !class_exists('MxChat_Multi_Bot_Manager')) {
        error_log("MXCHAT DEBUG: Using default options (no multi-bot or bot is 'default')");
        return array();
    }
    
    $bot_options = apply_filters('mxchat_get_bot_options', array(), $bot_id);
    
    if (!empty($bot_options)) {
        error_log("MXCHAT DEBUG: Got bot-specific options from filter");
        if (isset($bot_options['similarity_threshold'])) {
            error_log("  - similarity_threshold: " . $bot_options['similarity_threshold']);
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
    error_log("MXCHAT DEBUG: get_bot_pinecone_config called for bot: " . $bot_id);
    
    // If default bot or multi-bot add-on not active, use default Pinecone config
    if ($bot_id === 'default' || !class_exists('MxChat_Multi_Bot_Manager')) {
        error_log("MXCHAT DEBUG: Using default Pinecone config (no multi-bot or bot is 'default')");
        $addon_options = get_option('mxchat_pinecone_addon_options', array());
        $config = array(
            'use_pinecone' => (isset($addon_options['mxchat_use_pinecone']) && $addon_options['mxchat_use_pinecone'] === '1'),
            'api_key' => $addon_options['mxchat_pinecone_api_key'] ?? '',
            'host' => $addon_options['mxchat_pinecone_host'] ?? '',
            'namespace' => $addon_options['mxchat_pinecone_namespace'] ?? ''
        );
        error_log("MXCHAT DEBUG: Default config - use_pinecone: " . ($config['use_pinecone'] ? 'true' : 'false'));
        return $config;
    }
    
    error_log("MXCHAT DEBUG: Calling filter 'mxchat_get_bot_pinecone_config' for bot: " . $bot_id);
    
    // Hook for multi-bot add-on to provide bot-specific Pinecone config
    $bot_pinecone_config = apply_filters('mxchat_get_bot_pinecone_config', array(), $bot_id);
    
    if (!empty($bot_pinecone_config)) {
        error_log("MXCHAT DEBUG: Got bot-specific config from filter");
        error_log("  - use_pinecone: " . (isset($bot_pinecone_config['use_pinecone']) ? ($bot_pinecone_config['use_pinecone'] ? 'true' : 'false') : 'not set'));
        error_log("  - host: " . ($bot_pinecone_config['host'] ?? 'not set'));
        error_log("  - namespace: " . ($bot_pinecone_config['namespace'] ?? 'not set'));
    } else {
        error_log("MXCHAT DEBUG: Filter returned empty config!");
    }
    
    return is_array($bot_pinecone_config) ? $bot_pinecone_config : array();
}


// Updated function to check intents and invoke the callback function
private function mxchat_check_intent_and_invoke_callback($message, $user_id, $session_id) {
    global $wpdb;
    $chat_mode = get_option("mxchat_mode_{$session_id}", 'ai');
    
    //  Get the current bot_id
    $current_bot_id = $this->get_current_bot_id($session_id);
    
    // Generate the user embedding
    $user_embedding = $this->mxchat_generate_embedding($message, $this->options['api_key']);

    // Check if embedding generation returned an error
    if (is_array($user_embedding) && isset($user_embedding['error'])) {
        $error_message = $user_embedding['error'];
        $error_code = $user_embedding['error_code'] ?? 'embedding_error';

        // FIXED: Send error in appropriate format based on streaming mode
        if ($this->is_streaming) {
            echo "data: " . json_encode([
                'error' => true,
                'error_message' => $error_message,
                'error_code' => $error_code,
                'text' => $error_message,
                'message' => $error_message
            ]) . "\n\n";
            echo "data: [DONE]\n\n";
            flush();
        } else {
            wp_send_json_error([
                'error_message' => $error_message,
                'error_code' => $error_code
            ]);
        }
        wp_die();
    }

    // Check if embedding is valid
    if (!is_array($user_embedding) || empty($user_embedding)) {
        $error_message = esc_html__('Unable to process your message. The embedding service is not responding correctly.', 'mxchat');

        // FIXED: Send error in appropriate format based on streaming mode
        if ($this->is_streaming) {
            echo "data: " . json_encode([
                'error' => true,
                'error_message' => $error_message,
                'error_code' => 'invalid_embedding',
                'text' => $error_message,
                'message' => $error_message
            ]) . "\n\n";
            echo "data: [DONE]\n\n";
            flush();
        } else {
            wp_send_json_error([
                'error_message' => $error_message,
                'error_code' => 'invalid_embedding'
            ]);
        }
        wp_die();
    }

    // Fetch intents from the database
    $table_name = $wpdb->prefix . 'mxchat_intents';
    if ($chat_mode === 'agent') {
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE callback_function = %s AND (enabled = 1 OR enabled IS NULL)",
            'mxchat_handle_switch_to_chatbot_intent'
        );
        $intents = $wpdb->get_results($query);
    } else {
        $intents = $wpdb->get_results("SELECT * FROM $table_name WHERE enabled = 1 OR enabled IS NULL");
    }
    
    if (empty($intents)) {
        return false;
    }
    
    $highest_similarity = -INF;
    $matched_intent = null;
    
    //   Array to store action analysis for testing panel
    $action_analysis = [];
    
    foreach ($intents as $intent) {
        // Additional check for enabled state
        $is_enabled = isset($intent->enabled) ? (bool)$intent->enabled : true;
        if (!$is_enabled) {
            continue;
        }
        
        //  Check if this action is enabled for the current bot
        if (!$this->is_action_enabled_for_bot($intent, $current_bot_id)) {
            continue;
        }
        
        $intent_embedding_serialized = $intent->embedding_vector;
        $intent_embedding = $intent_embedding_serialized
            ? unserialize($intent_embedding_serialized, ['allowed_classes' => false])
            : null;
            
        if (!is_array($intent_embedding)) {
            continue;
        }
        
        $similarity = $this->mxchat_calculate_cosine_similarity($user_embedding, $intent_embedding);
        $intent_threshold = isset($intent->similarity_threshold) ? $intent->similarity_threshold : 0.85;
        
        //   Store action analysis data for testing panel
        $action_analysis[] = [
            'intent_label' => $intent->intent_label,
            'callback_function' => $intent->callback_function,
            'similarity' => round($similarity, 4),
            'similarity_percentage' => round($similarity * 100, 2),
            'threshold' => $intent_threshold,
            'threshold_percentage' => round($intent_threshold * 100, 2),
            'above_threshold' => $similarity >= $intent_threshold,
            'triggered' => false // Will be updated below if this intent is triggered
        ];
        
        if ($similarity >= $intent_threshold && $similarity > $highest_similarity) {
            $highest_similarity = $similarity;
            $matched_intent = $intent;
        }
    }
    
    //   Mark the triggered action if any
    if ($matched_intent) {
        foreach ($action_analysis as &$action) {
            if ($action['intent_label'] === $matched_intent->intent_label) {
                $action['triggered'] = true;
                break;
            }
        }
    }
    
    //   Sort actions by similarity (highest first) and store for testing panel
    usort($action_analysis, function($a, $b) {
        return $b['similarity'] <=> $a['similarity'];
    });
    
    // Store action analysis for testing panel capture
    $this->last_action_analysis = $action_analysis;
    
    // Around line 715 in your mxchat_check_intent_and_invoke_callback function
    if ($matched_intent) {
        // If the callback is a method on this instance (core callback), call it directly
        if (method_exists($this, $matched_intent->callback_function)) {
            $callback_result = call_user_func(
                [$this, $matched_intent->callback_function],
                $message,
                $user_id,
                $session_id,
                $matched_intent,
                $user_context ?? null
            );
        } else {
            // Otherwise, use apply_filters for add-on callbacks
            $callback_result = apply_filters(
                $matched_intent->callback_function, 
                false,
                $message, 
                $user_id, 
                $session_id,
                $matched_intent
            );
        }
        
        // Handle the callback result properly
        if ($callback_result !== false) {
            // If callback returned an array with chat_mode, use it directly
            if (is_array($callback_result) && isset($callback_result['chat_mode'])) {
                $this->fallbackResponse = $callback_result;
                return $callback_result; // Return the full array
            } else {
                $this->fallbackResponse = $callback_result;
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Check if an action is enabled for a specific bot
 */
private function is_action_enabled_for_bot($intent, $bot_id) {
    // If enabled_bots column doesn't exist or is null, assume it's enabled for all bots (backward compatibility)
    if (!isset($intent->enabled_bots) || empty($intent->enabled_bots)) {
        return true;
    }
    
    $enabled_bots = json_decode($intent->enabled_bots, true);
    
    // If JSON decode fails or returns empty array, assume enabled for all (backward compatibility)
    if (!is_array($enabled_bots) || empty($enabled_bots)) {
        return true;
    }
    
    // Check if the current bot is in the enabled bots list
    return in_array($bot_id, $enabled_bots);
}

// Helper function to clear PDF and Word document related transients
private function clear_pdf_transients($session_id) {
    // PDF transients
    delete_transient('mxchat_pdf_url_' . $session_id);
    delete_transient('mxchat_pdf_embeddings_' . $session_id);
    delete_transient('mxchat_include_pdf_in_context_' . $session_id);
    delete_transient('mxchat_waiting_for_pdf_url_' . $session_id);

    // Word document transients
    delete_transient('mxchat_word_url_' . $session_id);
    delete_transient('mxchat_word_filename_' . $session_id);
    delete_transient('mxchat_word_embeddings_' . $session_id);
    delete_transient('mxchat_include_word_in_context_' . $session_id);
    delete_transient('mxchat_waiting_for_word_' . $session_id);
}



//verified good
public function mxchat_handle_email_capture($message, $user_id, $session_id) {
    // Get the user's original instruction/message
    $user_instruction = esc_html($this->options['triggered_phrase_response'] ?? esc_html__("Please provide your email address.", 'mxchat'));
    
    // Set instruction for AI - just pass along what the user wanted to say
    $this->current_action_instruction = $user_instruction;
    
    // Set the transient to track email capture flow
    set_transient('mxchat_email_capture_' . $user_id, true, 5 * MINUTE_IN_SECONDS);
    
    // Return false to let the AI generate the response
    return false;
}

public function mxchat_generate_image($message, $user_id, $session_id) {
    //error_log("Starting image generation for message: " . $message);
    
    // Prepare a prompt for DALL-E
    $prompt = esc_html__('Create an image of ', 'mxchat') . sanitize_text_field($message);
    
    // Use the existing OpenAI API key
    $openai_api_key = sanitize_text_field($this->options['api_key']);
    
    // Call DALL-E to generate an image
    $image_response = $this->mxchat_generate_dalle_image($prompt, $openai_api_key);
    
    // Check if the response contains an image URL
    if (isset($image_response['imageUrl'])) {
        $image_url = esc_url_raw($image_response['imageUrl']);
        
        // Construct the HTML with a CSS class instead of inline styles
        $response_html = '<img src="' . esc_url($image_url) . '" alt="' . esc_attr__('Generated Image', 'mxchat') . '" class="mxchat-generated-image" />';
        $response_text = esc_html__('Here is the image I generated:', 'mxchat');
        
        // Save the bot message with both text and HTML
        $this->mxchat_save_chat_message($session_id, 'bot', $response_text);
        $this->mxchat_save_chat_message($session_id, 'bot', $response_html);
        
        // Set the fallback response for the chat handler
        $this->fallbackResponse = [
            'text' => $response_text,
            'html' => $response_html,
            'images' => [$image_url]
        ];
        
        // For debugging/verification - Use json_encode to verify what's being set
        //error_log("Image generation successful - fallbackResponse set: " . json_encode($this->fallbackResponse));

        // Return the response directly instead of relying on the property
        return $this->fallbackResponse;
    } else {
        $response_text = esc_html__("I'm sorry, but I couldn't generate an image based on your request.", 'mxchat');
        
        // Save the error message
        $this->mxchat_save_chat_message($session_id, 'bot', $response_text);
        
        // Set the fallback response for the chat handler
        $this->fallbackResponse = [
            'text' => $response_text,
            'html' => '',
            'images' => []
        ];
        
        //error_log("DALL-E image generation error: " . esc_html($image_response['error'] ?? 'Unknown error.'));
        //error_log("Error fallbackResponse set: " . json_encode($this->fallbackResponse));
        
        // Return the response directly instead of relying on the property
        return $this->fallbackResponse;
    }
}
private function mxchat_generate_dalle_image($prompt, $api_key, $model = 'dall-e-3', $timeout = 60) {
    $api_url = 'https://api.openai.com/v1/images/generations';
    $body = json_encode([
        'prompt' => sanitize_text_field($prompt),
        'n' => 1,
        'size' => '1024x1024',
        'model' => sanitize_text_field($model),
    ]);

    $args = [
        'body' => $body,
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . sanitize_text_field($api_key),
        ],
        'method' => 'POST',
        'timeout' => absint($timeout),
    ];

    $response = wp_remote_post($api_url, $args);

    if (is_wp_error($response)) {
        //error_log("DALL-E request failed: " . $response->get_error_message());
        return ['error' => esc_html__('Error generating image: ', 'mxchat') . $response->get_error_message()];
    }

    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($response_body['data'][0]['url'])) {
        return ['imageUrl' => esc_url_raw($response_body['data'][0]['url'])];
    } else {
        //error_log("DALL-E response error: " . wp_remote_retrieve_body($response));
        return ['error' => esc_html__('Failed to generate image.', 'mxchat')];
    }
}

/**
 * Handle web search requests.
 *
 * Sends the refined search query to the Brave Search API and uses the
 * results to generate a conversational response with the AI model.
 *
 * @since 1.0.0
 * @param string $message    The user's search query.
 * @param string $user_id    The user identifier.
 * @param string $session_id The current session ID.
 * @return array             Response array containing text with embedded HTML links
 */
public function mxchat_handle_search_request($message, $user_id, $session_id) {
    // Step 1: Interpret and refine the search query
    $refined_search_query = $this->mxchat_interpret_search_query($message);
    if (empty($refined_search_query)) {
        return array(
            'text' => esc_html__('I apologize, but could you please rephrase your search request?', 'mxchat'),
            'html' => ''
        );
    }
    
    // Retrieve and validate API settings
    $options = get_option('mxchat_options');
    $api_key = isset($options['brave_api_key']) ? sanitize_text_field($options['brave_api_key']) : '';
    $results_count = isset($options['brave_results_count']) ? absint($options['brave_results_count']) : 5;
    
    if (empty($api_key)) {
        return array(
            'text' => esc_html__('Search functionality is temporarily unavailable. Please try again later.', 'mxchat'),
            'html' => ''
        );
    }
    
    // Build the API request URL
    $api_url = add_query_arg(
        array(
            'q'                 => rawurlencode($refined_search_query),
            'count'             => $results_count,
            'text_decorations'  => 'true',
            'rich_data'         => 'true',
        ),
        'https://api.search.brave.com/res/v1/web/search'
    );
    
    // Attempt to retrieve cached results first
    $transient_key = 'mxchat_search_' . md5($refined_search_query);
    $results = get_transient($transient_key);
    
    if (false === $results) {
        // SECURITY FIX: Changed to wp_safe_remote_get
        $response = wp_safe_remote_get(
            $api_url,
            array(
                'headers' => array(
                    'Accept'              => 'application/json',
                    'Accept-Encoding'     => 'gzip',
                    'X-Subscription-Token'=> $api_key,
                ),
                'timeout' => 10,
            )
        );
        
        if (is_wp_error($response)) {
            return array(
                'text' => esc_html__('I encountered an error while searching. Please try again.', 'mxchat'),
                'html' => ''
            );
        }
        
        $results = json_decode(wp_remote_retrieve_body($response), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'text' => esc_html__('I received an invalid response from the search service.', 'mxchat'),
                'html' => ''
            );
        }
        
        // Cache results for one hour
        set_transient($transient_key, $results, HOUR_IN_SECONDS);
    }
    
    // Process results
    if (!empty($results['web']['results']) && is_array($results['web']['results'])) {
        // Create a more straightforward summary with HTML links
        $search_results_text = '';
        
        // Add a simple intro
        $search_results_text .= sprintf(
            esc_html__("Here's what I found about '%s':", 'mxchat'),
            esc_html($refined_search_query)
        );
        
        // Add the top results with HTML links
        foreach (array_slice($results['web']['results'], 0, 5) as $result) {
            $title = isset($result['title']) ? wp_strip_all_tags($result['title']) : '';
            $url = isset($result['url']) ? esc_url($result['url']) : '';
            $description = isset($result['description']) ? wp_strip_all_tags($result['description']) : '';
            
            // Add a line break after the intro
            $search_results_text .= '<br><br>';
            
            // Add title as a link
            $search_results_text .= sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a><br>',
                $url,
                $title
            );
            
            // Add a condensed description
            $search_results_text .= sprintf("%s", $description);
        }
        
        // Save to chat history
        $this->mxchat_save_chat_message($session_id, 'bot', $search_results_text);
        
        // Return the formatted text with embedded HTML links
        return array(
            'text' => $search_results_text,
            'html' => ''
        );
    } else {
        return array(
            'text' => sprintf(
                esc_html__('I searched for "%s" but couldn\'t find any relevant results. Would you like to try different search terms?', 'mxchat'),
                esc_html($refined_search_query)
            ),
            'html' => ''
        );
    }
}

//very good
/**
 * Handle image search requests from the chatbot
 *
 * @param string $message The user's search query
 * @param int $user_id The user's ID
 * @param string $session_id The chat session ID
 * @return array Response array with text and HTML content
 */
public function mxchat_handle_image_search_request($message, $user_id, $session_id) {
    // Step 1: Interpret the search query using the user's selected AI model
    $refined_search_query = $this->mxchat_interpret_search_query($message);

    // If no query was interpreted, return a fallback message
    if (empty($refined_search_query)) {
        return array(
            'text' => __("I'm sorry, I couldn't interpret your search query. Please specify what you'd like to see images of.", 'mxchat'),
            'html' => "",
        );
    }

    // Brave API URL
    $api_url = 'https://api.search.brave.com/res/v1/images/search';

    // Retrieve Brave API settings
    $options = get_option('mxchat_options');
    $api_key = isset($options['brave_api_key']) ? sanitize_text_field($options['brave_api_key']) : '';

    if (empty($api_key)) {
        return array(
            'text' => __("API key is not configured. Please set it in the Brave Search Settings.", 'mxchat'),
            'html' => "",
        );
    }

    $image_count = isset($options['brave_image_count']) ? intval($options['brave_image_count']) : 4;
    $safe_search = isset($options['brave_safe_search']) ? sanitize_text_field($options['brave_safe_search']) : 'strict';

    // Append query parameters based on settings
    $api_url = add_query_arg([
        'q' => rawurlencode($refined_search_query),
        'count' => $image_count,
        'safesearch' => $safe_search,
    ], $api_url);

    // Implement caching
    $transient_key = 'mxchat_image_search_' . md5($refined_search_query);
    $body = get_transient($transient_key);

    if (false === $body) {
        $args = [
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Encoding' => 'gzip',
                'X-Subscription-Token' => $api_key,
            ],
            'timeout' => 10,
        ];

        // SECURITY FIX: Changed to wp_safe_remote_get
        $response = wp_safe_remote_get($api_url, $args);

        if (is_wp_error($response)) {
            return array(
                'text' => __("I'm sorry, I couldn't retrieve any images based on your request.", 'mxchat'),
                'html' => "",
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        set_transient($transient_key, $body, HOUR_IN_SECONDS);
    }

    // Process the API response
    if (isset($body['results']) && is_array($body['results']) && count($body['results']) > 0) {
        $html_output = '<div class="mxchat-image-gallery">';
        
        // Get the configured image count (1-6)
        $display_count = isset($options['brave_image_count']) ? intval($options['brave_image_count']) : 4;
        $display_count = min($display_count, count($body['results'])); // Make sure we don't exceed available images
        
        // Use only the requested number of images
        for ($i = 0; $i < $display_count; $i++) {
            $image = $body['results'][$i];
            $image_url = isset($image['url']) ? esc_url($image['url']) : '';
            $thumbnail_url = isset($image['thumbnail']['src']) ? esc_url($image['thumbnail']['src']) : '';
            $title = isset($image['title']) ? esc_html($image['title']) : esc_html__('Image', 'mxchat');

            if ($image_url && $thumbnail_url) {
                $html_output .= '<div class="mxchat-image-item">';
                $html_output .= '<strong class="mxchat-image-title">' . $title . '</strong>';
                $html_output .= '<a href="' . $image_url . '" target="_blank" rel="noopener noreferrer" class="mxchat-image-link">';
                $html_output .= '<img src="' . $thumbnail_url . '" alt="' . $title . '" class="mxchat-image-thumbnail">';
                $html_output .= '</a></div>';
            }
        }

        $html_output .= '</div>';

        // Create response text
        $response_text = sprintf(__("Here are some images of %s:", 'mxchat'), $refined_search_query);
        
        // Save both response text and HTML to chat history
        $this->mxchat_save_chat_message($session_id, 'bot', $response_text);
        $this->mxchat_save_chat_message($session_id, 'bot', $html_output);

        // Return the combined response
        return array(
            'text' => $response_text,
            'html' => $html_output,
        );
    } else {
        $response_text = __("I'm sorry, I couldn't retrieve any images based on your request.", 'mxchat');
        
        // Save the error message to chat history
        $this->mxchat_save_chat_message($session_id, 'bot', $response_text);
        
        return array(
            'text' => $response_text,
            'html' => "",
        );
    }
}

/**
 * Interpret the search query using the user's selected AI model
 * 
 * @param string $user_query The original query from the user
 * @return string The refined search query
 */
public function mxchat_interpret_search_query($user_query) {
    $system_prompt = esc_html__("Interpret the user's request to provide only the essential keywords or phrases for image searching. Remove conversational language, politeness, or extra context. Return a concise search query that doesn't lose any of the original meaning.", 'mxchat');
    
    // Get options and determine the selected model
    $options = $this->options ?? get_option('mxchat_options');
    $selected_model = isset($options['model']) ? $options['model'] : 'gpt-5.1-chat-latest';
    
    // Extract model prefix to determine the provider
    $model_parts = explode('-', $selected_model);
    $provider = strtolower($model_parts[0]);
    
    // Determine which API key to use based on the provider
    switch ($provider) {
        case 'gemini':
            $api_key = isset($options['gemini_api_key']) ? sanitize_text_field($options['gemini_api_key']) : '';
            if (empty($api_key)) {
                return sanitize_text_field($user_query); // Default to original query if API key missing
            }
            return $this->interpret_query_with_gemini($user_query, $system_prompt, $api_key, $selected_model);
            
        case 'claude':
            $api_key = isset($options['claude_api_key']) ? sanitize_text_field($options['claude_api_key']) : '';
            if (empty($api_key)) {
                return sanitize_text_field($user_query);
            }
            return $this->interpret_query_with_claude($user_query, $system_prompt, $api_key, $selected_model);
            
        case 'grok':
            $api_key = isset($options['xai_api_key']) ? sanitize_text_field($options['xai_api_key']) : '';
            if (empty($api_key)) {
                return sanitize_text_field($user_query);
            }
            return $this->interpret_query_with_xai($user_query, $system_prompt, $api_key, $selected_model);
            
        case 'deepseek':
            $api_key = isset($options['deepseek_api_key']) ? sanitize_text_field($options['deepseek_api_key']) : '';
            if (empty($api_key)) {
                return sanitize_text_field($user_query);
            }
            return $this->interpret_query_with_deepseek($user_query, $system_prompt, $api_key, $selected_model);
            
        case 'gpt':
        default:
            // Default to OpenAI for custom models or unrecognized prefixes
            $api_key = isset($options['api_key']) ? sanitize_text_field($options['api_key']) : '';
            if (empty($api_key)) {
                return sanitize_text_field($user_query);
            }
            return $this->interpret_query_with_openai($user_query, $system_prompt, $api_key, $selected_model);
    }
}

/**
 * Interpret query using OpenAI models
 */
private function interpret_query_with_openai($user_query, $system_prompt, $api_key, $model = 'gpt-5.1-chat-latest') {
    $url = 'https://api.openai.com/v1/chat/completions';
    $args = [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => sanitize_text_field($user_query)],
            ],
            'temperature' => 0.2,
            'max_tokens' => 20,
        ]),
        'method' => 'POST',
        'timeout' => 15,
    ];

    $response = wp_remote_post($url, $args);
    if (is_wp_error($response)) {
        return sanitize_text_field($user_query);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return isset($body['choices'][0]['message']['content']) 
        ? sanitize_text_field(trim($body['choices'][0]['message']['content'])) 
        : sanitize_text_field($user_query);
}

/**
 * Interpret query using Claude models
 */
private function interpret_query_with_claude($user_query, $system_prompt, $api_key, $model) {
    $url = 'https://api.anthropic.com/v1/messages';
    
    $args = [
        'headers' => [
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01',
        ],
        'body' => wp_json_encode([
            'model' => $model,
            'system' => $system_prompt,
            'messages' => [
                ['role' => 'user', 'content' => sanitize_text_field($user_query)]
            ],
            'max_tokens' => 20,
            'temperature' => 0.2,
        ]),
        'method' => 'POST',
        'timeout' => 15,
    ];

    $response = wp_remote_post($url, $args);
    if (is_wp_error($response)) {
        return sanitize_text_field($user_query);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!empty($body['content'][0]['text'])) {
        return sanitize_text_field(trim($body['content'][0]['text']));
    }
    
    return sanitize_text_field($user_query);
}

/**
 * Interpret query using Gemini models
 */
private function interpret_query_with_gemini($user_query, $system_prompt, $api_key, $model) {
    // Use v1beta for preview models, v1 for stable models
    $api_version = (strpos($model, 'preview') !== false || strpos($model, 'exp') !== false) ? 'v1beta' : 'v1';

    $url = "https://generativelanguage.googleapis.com/{$api_version}/models/{$model}:generateContent?key=" . urlencode($api_key);
    
    $args = [
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode([
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $system_prompt . "\n\nQuery: " . sanitize_text_field($user_query)]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 20,
            ],
        ]),
        'method' => 'POST',
        'timeout' => 15,
    ];
    
    $response = wp_remote_post($url, $args);
    if (is_wp_error($response)) {
        return sanitize_text_field($user_query);
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!empty($body['candidates'][0]['content']['parts'][0]['text'])) {
        return sanitize_text_field(trim($body['candidates'][0]['content']['parts'][0]['text']));
    }
    
    return sanitize_text_field($user_query);
}

/**
 * Interpret query using X.AI (Grok) models
 */
private function interpret_query_with_xai($user_query, $system_prompt, $api_key, $model) {
    $url = 'https://api.xai.com/v1/chat/completions';
    
    $args = [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => wp_json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => sanitize_text_field($user_query)],
            ],
            'temperature' => 0.2,
            'max_tokens' => 20,
        ]),
        'method' => 'POST',
        'timeout' => 15,
    ];
    
    $response = wp_remote_post($url, $args);
    if (is_wp_error($response)) {
        return sanitize_text_field($user_query);
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($body['choices'][0]['message']['content'])) {
        return sanitize_text_field(trim($body['choices'][0]['message']['content']));
    }
    
    return sanitize_text_field($user_query);
}

/**
 * Interpret query using DeepSeek models
 */
private function interpret_query_with_deepseek($user_query, $system_prompt, $api_key, $model) {
    $url = 'https://api.deepseek.com/v1/chat/completions';
    
    $args = [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => wp_json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => sanitize_text_field($user_query)],
            ],
            'temperature' => 0.2,
            'max_tokens' => 20,
        ]),
        'method' => 'POST',
        'timeout' => 15,
    ];
    
    $response = wp_remote_post($url, $args);
    if (is_wp_error($response)) {
        return sanitize_text_field($user_query);
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($body['choices'][0]['message']['content'])) {
        return sanitize_text_field(trim($body['choices'][0]['message']['content']));
    }
    
    return sanitize_text_field($user_query);
}

//very good
private function add_email_to_loops($email) {
    // Sanitize the email
    $email = sanitize_email($email);

    // Retrieve and sanitize options
    $api_key = isset($this->options['loops_api_key']) ? sanitize_text_field($this->options['loops_api_key']) : '';
    $mailing_list_id = isset($this->options['loops_mailing_list']) ? sanitize_text_field($this->options['loops_mailing_list']) : '';

    // Check for missing API key or mailing list ID
    if (empty($api_key) || empty($mailing_list_id)) {
        //error_log(esc_html__('Loops API key or mailing list ID is missing.', 'mxchat'));
        return;
    }

    $data = array(
        'email'        => $email,
        'subscribed'   => true,
        'source'       => __('MxChat AI Chatbot', 'mxchat'),
        'mailingLists' => array($mailing_list_id => true),
    );

    $url = 'https://app.loops.so/api/v1/contacts/create';
    $args = array(
        'body'    => wp_json_encode($data),
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ),
        'method'  => 'POST',
        'timeout' => 45,
    );

    $response = wp_remote_post($url, $args);

    // Handle errors in the API request
    if (is_wp_error($response)) {
        //error_log(esc_html__('Error adding email to Loops: ', 'mxchat') . $response->get_error_message());
        return;
    }

    // Check for non-200 HTTP responses
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code != 200) {
        $response_body = wp_remote_retrieve_body($response);
        //error_log(esc_html__('Loops API responded with code ', 'mxchat') . $response_code . ': ' . $response_body);
    }
}

public function mxchat_handle_pdf_discussion($message, $user_id, $session_id) {
    // Get the maximum number of pages allowed from admin settings
    $max_pages = isset($this->options['pdf_max_pages']) ? intval($this->options['pdf_max_pages']) : 69;

    // Retrieve options for dynamic texts
    $trigger_text = $this->options['pdf_intent_trigger_text'] ?? __("Please provide the URL to the PDF you'd like to discuss.", 'mxchat');
    $success_text = $this->options['pdf_intent_success_text'] ?? __("I've processed the PDF. What questions do you have about it?", 'mxchat');
    $error_text = $this->options['pdf_intent_error_text'] ?? __("Sorry, I couldn't process the PDF. Please ensure it's a valid file.", 'mxchat');

    // Check for explicit request for new PDF
    $new_pdf_requested = stripos($message, 'new') !== false ||
                        stripos($message, 'another') !== false ||
                        stripos($message, 'different') !== false;

    // If user mentions adding/reading a PDF, set waiting flag
    if (stripos($message, 'pdf') !== false ||
        stripos($message, 'document') !== false ||
        stripos($message, 'read') !== false) {
        set_transient('mxchat_waiting_for_pdf_url_' . $session_id, true, HOUR_IN_SECONDS);
        $this->fallbackResponse['text'] = $trigger_text;
        return;
    }

    // If we're waiting for a URL or user requested new PDF
    if ($new_pdf_requested || get_transient('mxchat_waiting_for_pdf_url_' . $session_id)) {
        if (preg_match('/https?:\/\/[^\s"]+/i', $message, $matches)) {
            // Process URL... (rest of your existing URL processing code)
        } else {
            $this->fallbackResponse['text'] = $trigger_text;
        }
        return;
    }

    // Default to proceeding with conversation if no specific PDF action is needed
    $this->fallbackResponse['text'] = '';
}


/**
 * Enhanced fetch_and_split_pdf_pages with SSRF protection
 */
private function fetch_and_split_pdf_pages($pdf_source, $max_pages) {
    // CLEAR DEBUG LOGGING
    //error_log("=== MXCHAT PDF PROCESSING START ===");
    //error_log("PDF Source: " . $pdf_source);
    //error_log("Max Pages: " . $max_pages);
    //error_log("Session ID: " . ($this->session_id ?? 'not set'));
    
    // Check if Advanced Claude Toolbar is available and enabled
    $claude_available = function_exists('mxchatACT_is_advanced_claude_enabled');
    $claude_enabled = $claude_available ? mxchatACT_is_advanced_claude_enabled() : false;
    
    //error_log("Claude Function Available: " . ($claude_available ? 'YES' : 'NO'));
    //error_log("Claude Enabled: " . ($claude_enabled ? 'YES' : 'NO'));
    
    if ($claude_available && $claude_enabled) {
        //error_log("🚀 ATTEMPTING CLAUDE PROCESSING...");
        
        // Attempt Claude processing first
        $claude_result = apply_filters('mxchat_process_pdf_advanced', false, $pdf_source, $max_pages, $this->session_id);
        
        if ($claude_result !== false && is_array($claude_result) && !empty($claude_result)) {
            //error_log("✅ CLAUDE PROCESSING SUCCESSFUL!");
            //error_log("Claude returned " . count($claude_result) . " processed pages");
            
            // Log first page details for verification
            if (isset($claude_result[0])) {
                $first_page = $claude_result[0];
                //error_log("First page enhanced: " . (isset($first_page['enhanced']) && $first_page['enhanced'] ? 'YES' : 'NO'));
                //error_log("Processing method: " . ($first_page['processing_method'] ?? 'not set'));
                //error_log("First page text preview: " . substr($first_page['text'] ?? '', 0, 100) . "...");
            }
            
            //error_log("=== MXCHAT PDF PROCESSING END (CLAUDE) ===");
            return $claude_result;
        } else {
            //error_log("❌ CLAUDE PROCESSING FAILED or returned invalid result");
            //error_log("Claude result type: " . gettype($claude_result));
            if (is_array($claude_result)) {
                //error_log("Claude result count: " . count($claude_result));
            }
        }
    }
    
    // Fallback to basic processing
    //error_log("🔄 FALLING BACK TO BASIC PDF PROCESSING...");
    
    $upload_dir = wp_upload_dir();
    $temp_file = null;
    
    try {
        // Your existing basic processing code here...
        // (I'll include the key parts with debug logging)
        
        if (filter_var($pdf_source, FILTER_VALIDATE_URL)) {
            //error_log("Downloading PDF from URL...");
            
            // SECURITY FIX: Validate URL before processing
            if (!$this->mxchat_is_safe_pdf_url($pdf_source)) {
                //error_log("❌ SECURITY: Blocked unsafe PDF URL");
                return false;
            }
            
            $temp_file = wp_tempnam($pdf_source);
            
            // SECURITY FIX: Changed from wp_remote_get to wp_safe_remote_get
            $response = wp_safe_remote_get($pdf_source, [
                'timeout' => 60,
                'headers' => ['User-Agent' => 'MxChat PDF Processor']
            ]);
            
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                $error_message = is_wp_error($response) ? $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response);
                //error_log("❌ BASIC PROCESSING: Failed to download PDF: " . $error_message);
                return false;
            }
            
            file_put_contents($temp_file, wp_remote_retrieve_body($response));
            //error_log("✅ PDF downloaded successfully");
        } else {
            $temp_file = $pdf_source;
            //error_log("Using local PDF file: " . $temp_file);
        }
        
        // Parse PDF
        //error_log("Parsing PDF with basic parser...");
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($temp_file);
        $pages = $pdf->getPages();
        
        //error_log("PDF contains " . count($pages) . " pages");
        
        if (count($pages) > $max_pages) {
            //error_log("❌ BASIC PROCESSING: Too many pages (" . count($pages) . " > " . $max_pages . ")");
            if (filter_var($pdf_source, FILTER_VALIDATE_URL) && $temp_file) {
                unlink($temp_file);
            }
            return 'too_many_pages';
        }
        
        $embeddings = [];
        $processed_pages = 0;
        
        foreach ($pages as $page_number => $page) {
            $text = $page->getText();
            
            if (empty(trim($text))) {
                //error_log("Skipping empty page: " . ($page_number + 1));
                continue;
            }
            
            $text = $this->mxchat_clean_text($text);
            
            $embedding = $this->mxchat_generate_embedding(
                __("Page ", 'mxchat') . ($page_number + 1) . ": " . $text,
                $this->options['api_key']
            );
            
            if ($embedding) {
                $embeddings[] = [
                    'page_number' => $page_number + 1,
                    'embedding' => $embedding,
                    'text' => $text,
                    'enhanced' => false, // CLEARLY MARK AS BASIC
                    'processing_method' => 'basic_pdf_parser'
                ];
                $processed_pages++;
            }
        }
        
        //error_log("✅ BASIC PROCESSING COMPLETE: " . $processed_pages . " pages processed");
        
        // Cleanup
        if (filter_var($pdf_source, FILTER_VALIDATE_URL) && $temp_file && file_exists($temp_file)) {
            unlink($temp_file);
        }
        
        //error_log("=== MXCHAT PDF PROCESSING END (BASIC) ===");
        return $embeddings;
        
    } catch (\Exception $e) {
        //error_log("❌ BASIC PROCESSING ERROR: " . $e->getMessage());
        if (filter_var($pdf_source, FILTER_VALIDATE_URL) && $temp_file && file_exists($temp_file)) {
            unlink($temp_file);
        }
        //error_log("=== MXCHAT PDF PROCESSING END (ERROR) ===");
        return false;
    }
}


/**
 * Validate PDF URL for security
 * Prevents SSRF attacks by blocking dangerous URLs
 */
 
private function mxchat_is_safe_pdf_url($url) {
    // Use WordPress core function for comprehensive validation
    // This blocks localhost, private IPs, and reserved IP ranges
    $validated_url = wp_http_validate_url($url);
    
    if ($validated_url === false) {
        return false;
    }
    
    // Additional check: only allow HTTP/HTTPS schemes
    $parsed = parse_url($url);
    if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'], true)) {
        return false;
    }
    
    return true;
}


private function mxchat_clean_text($text) {
    // Remove excessive whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Remove control characters except newlines and tabs
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    
    // Normalize line endings
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    
    // Trim whitespace
    $text = trim($text);
    
    return $text;
}

private function find_relevant_pdf_pages($query_embedding, $embeddings) {
    //error_log(esc_html__("find_relevant_pdf_pages called.", 'mxchat'));

    $most_relevant = null;
    $highest_similarity = -INF;

    foreach ($embeddings as $page_data) {
        $similarity = $this->mxchat_calculate_cosine_similarity($query_embedding, $page_data['embedding']);

        if ($similarity > $highest_similarity) {
            $highest_similarity = $similarity;
            $most_relevant = $page_data['page_number'];
        }
    }

    if (!is_null($most_relevant)) {
        $page_numbers = range(max(1, $most_relevant - 1), min(count($embeddings), $most_relevant + 1));
        return array_filter($embeddings, function ($page) use ($page_numbers) {
            return in_array($page['page_number'], $page_numbers);
        });
    }

    return [];
}


public function handle_pdf_upload() {
    check_ajax_referer('mxchat_chat_nonce', 'nonce');

    if (!isset($_FILES['pdf_file']) || !isset($_POST['session_id'])) {
        wp_send_json_error(esc_html__('Missing required parameters.', 'mxchat'));
        return;
    }

    // SECURITY FIX: Check if PDF uploads are enabled in settings
    $options = get_option('mxchat_options', array());
    $show_pdf_button = isset($options['show_pdf_upload_button']) ? $options['show_pdf_upload_button'] : 'on';
    
    if ($show_pdf_button !== 'on') {
        wp_send_json_error(esc_html__('PDF uploads are currently disabled.', 'mxchat'));
        return;
    }

    $file = $_FILES['pdf_file'];
    $session_id = sanitize_text_field($_POST['session_id']);
    $original_filename = sanitize_text_field($file['name']);

    // SECURITY FIX: Verify session ownership before allowing upload
    $current_user_identifier = MxChat_User::mxchat_get_user_identifier();
    $session_owner = get_option("mxchat_session_owner_{$session_id}");
    
    if ($session_owner && $session_owner !== $current_user_identifier) {
        wp_send_json_error(esc_html__('Unauthorized access.', 'mxchat'));
        return;
    }

    $file_type = wp_check_filetype($file['name'], ['pdf' => 'application/pdf']);
    if ($file_type['type'] !== 'application/pdf') {
        wp_send_json_error(esc_html__('Invalid file type. Only PDF files are allowed.', 'mxchat'));
        return;
    }

    $upload_dir = wp_upload_dir();
    
    // SECURITY FIX: Generate random filename without exposing session_id
    $random_string = wp_generate_password(20, false, false); // 20 char alphanumeric string
    $pdf_filename = 'mxchat_' . $random_string . '_' . time() . '.pdf';
    $pdf_path = $upload_dir['path'] . '/' . $pdf_filename;

    if (!move_uploaded_file($file['tmp_name'], $pdf_path)) {
        wp_send_json_error(esc_html__('Failed to upload file.', 'mxchat'));
        return;
    }

    $this->clear_pdf_transients($session_id);

    $max_pages = isset($this->options['pdf_max_pages']) ? intval($this->options['pdf_max_pages']) : 69;
    $embeddings = $this->fetch_and_split_pdf_pages($pdf_path, $max_pages);

    if ($embeddings === 'too_many_pages') {
        unlink($pdf_path);
        $error_message = sprintf(
            $this->options['pdf_intent_error_text'] ??
            esc_html__("The provided PDF exceeds the maximum allowed limit of %d pages. Please provide a smaller document.", 'mxchat'),
            $max_pages
        );
        wp_send_json_error($error_message);
        return;
    }

    if ($embeddings === false || empty($embeddings)) {
        unlink($pdf_path);
        $error_message = $this->options['pdf_intent_error_text'] ??
            esc_html__('The uploaded PDF appears to be empty or contains unsupported content.', 'mxchat');
        wp_send_json_error($error_message);
        return;
    }

    if (!empty($embeddings)) {
        // Store the mapping between session and the random filename
        set_transient('mxchat_pdf_url_' . $session_id, $pdf_path, HOUR_IN_SECONDS);
        set_transient('mxchat_pdf_filename_' . $session_id, $original_filename, HOUR_IN_SECONDS);
        set_transient('mxchat_pdf_embeddings_' . $session_id, $embeddings, HOUR_IN_SECONDS);
        set_transient('mxchat_include_pdf_in_context_' . $session_id, true, HOUR_IN_SECONDS);

        $success_message = $this->options['pdf_intent_success_text'] ??
            esc_html__("I've processed the PDF. What questions do you have about it?", 'mxchat');

        wp_send_json_success([
            'message' => $success_message,
            'filename' => $original_filename
        ]);
        return;
    }

    unlink($pdf_path);
    $error_message = $this->options['pdf_intent_error_text'] ??
        esc_html__('Sorry, I couldn\'t process the PDF. Please ensure it\'s a valid file.', 'mxchat');
    wp_send_json_error($error_message);
    return;
}
public function handle_pdf_remove() {
    check_ajax_referer('mxchat_chat_nonce', 'nonce');

    if (empty($_POST['session_id'])) {
        wp_send_json_error(esc_html__('Session ID missing.', 'mxchat'));
        wp_die();
    }

    $session_id = sanitize_text_field($_POST['session_id']);
    $pdf_path = get_transient('mxchat_pdf_url_' . $session_id);

    if ($pdf_path && file_exists($pdf_path)) {
        unlink($pdf_path);
    }

    $this->clear_pdf_transients($session_id);

    wp_send_json_success([
        'message' => esc_html__('PDF removed successfully.', 'mxchat')
    ]);
    wp_die();
}


function mxchat_fetch_new_messages() {
    $session_id = sanitize_text_field($_POST['session_id']);
    $last_seen_id = sanitize_text_field($_POST['last_seen_id']);
    $persistence_enabled = $_POST['persistence_enabled'] === 'true';
    $initial_timestamp = isset($_POST['initial_timestamp']) ? intval($_POST['initial_timestamp']) : 0;

    if (empty($session_id)) {
        //error_log(esc_html__('Fetch new messages error: Session ID missing.', 'mxchat'));
        wp_send_json_error(['message' => esc_html__('Session ID missing.', 'mxchat')]);
        wp_die();
    }

    $history = get_option("mxchat_history_{$session_id}", []);

    error_log("MxChat WhatsApp DEBUG: Fetch new messages for session {$session_id}");
    error_log("MxChat WhatsApp DEBUG: last_seen_id = " . var_export($last_seen_id, true));
    error_log("MxChat WhatsApp DEBUG: History count = " . count($history));
    error_log("MxChat WhatsApp DEBUG: Full history = " . print_r($history, true));

    $new_messages = array_filter($history, function ($message) use ($last_seen_id, $persistence_enabled, $initial_timestamp) {
        error_log("MxChat WhatsApp DEBUG: Checking message - ID: " . ($message['id'] ?? 'NO_ID') . ", Role: " . ($message['role'] ?? 'NO_ROLE'));

        // If persistence is enabled, show all new messages
        if ($persistence_enabled) {
            $has_id = !empty($message['id']);
            $is_agent = $message['role'] === 'agent';

            // If last_seen_id is empty, 'NaN', or invalid, show all agent messages
            if (empty($last_seen_id) || $last_seen_id === 'NaN' || $last_seen_id === 'undefined') {
                $is_newer = true;
            } else {
                $is_newer = strcmp($message['id'] ?? '', $last_seen_id) > 0;
            }

            error_log("MxChat WhatsApp DEBUG: has_id={$has_id}, is_newer={$is_newer}, is_agent={$is_agent}");

            return $has_id && $is_newer && $is_agent;
        }

        // If persistence is disabled, only show messages after initial timestamp
        return !empty($message['id']) &&
               $message['role'] === 'agent' &&
               $message['timestamp'] > $initial_timestamp;
    });

    error_log("MxChat WhatsApp DEBUG: Filtered messages count = " . count($new_messages));

    wp_send_json_success([
        'new_messages' => array_values($new_messages)
    ]);
    wp_die();
}
public function mxchat_live_agent_handover($message, $user_id, $session_id) {
    // First check if live agents are available
    $live_agent_available = $this->options['live_agent_status'] ?? 'off';
    if ($live_agent_available !== 'on') {
        $away_message = $this->options['live_agent_away_message'] ?? 'Sorry, live agents are currently unavailable. I can continue helping you as an AI assistant.';
        $this->fallbackResponse = [
            'text' => $away_message,
            'html' => '',
            'images' => [],
            'chat_mode' => 'ai'
        ];
        wp_send_json([
            'text' => $away_message,
            'html' => '',
            'chat_mode' => 'ai',
            'session_id' => $session_id
        ]);
        wp_die();
    }

    $slack_bot_token = $this->options['live_agent_bot_token'] ?? '';
    
    if (empty($slack_bot_token)) {
        return false;
    }

    // Check if channel already exists for this session
    $channel_id = get_option("mxchat_channel_{$session_id}", '');
    
    if (empty($channel_id)) {
        // Create new channel with session ID as name
            $channel_name = $this->generate_channel_name($session_id);
        
        //error_log("Attempting to create channel: $channel_name");
        
        $response = wp_remote_post('https://slack.com/api/conversations.create', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $slack_bot_token
            ],
            'body' => json_encode([
                'name' => $channel_name,
                'is_private' => false // Public channel - anyone in workspace can join
            ])
        ]);
        
        if (!is_wp_error($response)) {
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            
            //error_log("Channel creation response: " . $response_body);
            
            if (isset($response_data['ok']) && $response_data['ok']) {
                $channel_id = $response_data['channel']['id'];
                $actual_channel_name = $response_data['channel']['name'] ?? 'unknown';
                //error_log("Channel created successfully: ID=$channel_id, Name=$actual_channel_name");
                update_option("mxchat_channel_{$session_id}", $channel_id);
                
                // Auto-invite agents to the channel
                $agent_user_ids = $this->options['live_agent_user_ids'] ?? '';
                
                if (!empty($agent_user_ids)) {
                    // Parse user IDs (one per line)
                    $user_ids = array_filter(array_map('trim', explode("\n", $agent_user_ids)));
                    
                    foreach ($user_ids as $user_id_to_invite) {
                        //error_log("Inviting user to channel: $user_id_to_invite");
                        
                        $invite_response = wp_remote_post('https://slack.com/api/conversations.invite', [
                            'headers' => [
                                'Content-Type' => 'application/json',
                                'Authorization' => 'Bearer ' . $slack_bot_token
                            ],
                            'body' => json_encode([
                                'channel' => $channel_id,
                                'users' => $user_id_to_invite
                            ])
                        ]);
                        
                        if (!is_wp_error($invite_response)) {
                            $invite_body = wp_remote_retrieve_body($invite_response);
                            $invite_data = json_decode($invite_body, true);
                            //error_log("Invite response for $user_id_to_invite: " . $invite_body);
                            
                            if (isset($invite_data['ok']) && $invite_data['ok']) {
                                //error_log("Successfully invited user $user_id_to_invite to channel");
                            } else {
                                //error_log("Failed to invite user $user_id_to_invite: " . ($invite_data['error'] ?? 'Unknown error'));
                            }
                        } else {
                            //error_log("WP Error inviting user $user_id_to_invite: " . $invite_response->get_error_message());
                        }
                    }
                } else {
                    //error_log("No agent user IDs configured for auto-invite");
                }
            } else {
                //error_log("Channel creation failed: " . ($response_data['error'] ?? 'Unknown error'));
            }
        } else {
            //error_log("WP Error creating channel: " . $response->get_error_message());
        }
        
        if (empty($channel_id)) {
            return false; // Failed to create channel
        }
    }

    // Get recent chat history
    $history = get_option("mxchat_history_{$session_id}", []);
    $recent_history = array_slice($history, -5);

    // Format conversation context
    $conversation_context = "";
    if (!empty($recent_history)) {
        $conversation_context = "*Recent Conversation:*\n";
        foreach ($recent_history as $hist_message) {
            $role_display = $hist_message['role'] === 'user' ? 'User' : 'AI';
            $conversation_context .= ">{$role_display}: {$hist_message['content']}\n";
        }
        $conversation_context .= "\n";
    }

    update_option("mxchat_mode_{$session_id}", 'agent');

    // Send message to channel
    $channel_message = "ðŸ”” *New Live Agent Request*\n\n";
    $channel_message .= "*Session ID:* `{$session_id}`\n";
    $channel_message .= "*User ID:* `{$user_id}`\n\n";
    
    if (!empty($conversation_context)) {
        $channel_message .= $conversation_context;
    }
    
    $channel_message .= "*Current Message:*\n{$message}\n\n";
    $channel_message .= "_Reply directly in this channel - all messages will go to the user_";

    wp_remote_post('https://slack.com/api/chat.postMessage', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $slack_bot_token
        ],
        'body' => json_encode([
            'channel' => $channel_id,
            'text' => $channel_message,
            'mrkdwn' => true
        ])
    ]);

    $success_message = $this->options['live_agent_notification_message'] ?? 'Live agent has been notified.';
    $this->mxchat_save_chat_message($session_id, 'bot', $success_message);

    $this->fallbackResponse = [
        'text' => $success_message,
        'html' => '',
        'images' => [],
        'chat_mode' => 'agent'
    ];

    wp_send_json([
        'success' => true,
        'text' => $success_message,
        'html' => '',
        'chat_mode' => 'agent',
        'session_id' => $session_id,
        'fallbackResponse' => $this->fallbackResponse
    ]);
    wp_die();
}

private function generate_channel_name($session_id) {
    $email = null;
    $name = null;
    
    // 1. First priority: Check if user is logged in and get their info
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        if (!empty($current_user->user_email)) {
            $email = $current_user->user_email;
            //error_log("[DEBUG] Using logged-in user email for channel: {$email}");
        }
        if (!empty($current_user->display_name)) {
            $name = $current_user->display_name;
            //error_log("[DEBUG] Using logged-in user name for channel: {$name}");
        }
    }
    
    // 2. Second priority: Check for saved email/name from "require email to chat" option
    if (empty($email)) {
        $email_option_key = "mxchat_email_{$session_id}";
        $saved_email = get_option($email_option_key);
        if (!empty($saved_email)) {
            $email = $saved_email;
            //error_log("[DEBUG] Using saved email from session for channel: {$email}");
        }
    }
    
    if (empty($name)) {
        $name_option_key = "mxchat_name_{$session_id}";
        $saved_name = get_option($name_option_key);
        if (!empty($saved_name)) {
            $name = $saved_name;
            //error_log("[DEBUG] Using saved name from session for channel: {$name}");
        }
    }
    
    // 3. Third priority: Check existing chat transcript for email/name
    if (empty($email) || empty($name)) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mxchat_chat_transcripts';
        $existing_data = $wpdb->get_row($wpdb->prepare(
            "SELECT user_email, user_name FROM $table_name WHERE session_id = %s AND (user_email IS NOT NULL OR user_name IS NOT NULL) LIMIT 1",
            $session_id
        ));
        
        if ($existing_data) {
            if (empty($email) && !empty($existing_data->user_email)) {
                $email = $existing_data->user_email;
                //error_log("[DEBUG] Using email from chat transcript for channel: {$email}");
            }
            if (empty($name) && !empty($existing_data->user_name)) {
                $name = $existing_data->user_name;
                //error_log("[DEBUG] Using name from chat transcript for channel: {$name}");
            }
        }
    }
    
    // 4. Generate channel name based on priority: Name > Email > Session ID
    $channel_name = '';
    
    if (!empty($name)) {
        // Convert name to valid Slack channel name
        $base_name = strtolower(trim($name));
        // Replace spaces and invalid characters
        $base_name = preg_replace('/[^a-z0-9\s]/', '', $base_name);
        $base_name = preg_replace('/\s+/', '-', $base_name);
        $base_name = trim($base_name, '-');
        
        // Get last 4 characters of session ID for uniqueness
        $session_suffix = substr($session_id, -4);
        $channel_name = 'chat-' . $base_name . '-' . strtolower($session_suffix);
        
        // Slack channel names have a 21 character limit
        if (strlen($channel_name) > 21) {
            // Calculate available space for name (21 - 'chat-' - '-' - session_suffix)
            $available_space = 21 - 5 - 1 - strlen($session_suffix); // 'chat-' = 5, '-' = 1
            $truncated_name = substr($base_name, 0, $available_space);
            $truncated_name = rtrim($truncated_name, '-'); // Remove trailing hyphen
            $channel_name = 'chat-' . $truncated_name . '-' . strtolower($session_suffix);
        }
        
        //error_log("[DEBUG] Using name for channel: {$channel_name} (from name: {$name})");
        
    } elseif (!empty($email)) {
        // Convert email to valid Slack channel name (your existing logic)
        $channel_name = 'chat-' . strtolower(str_replace(['@', '.', '+', '_'], ['-at-', '-', '-plus-', '-'], $email));
        // Remove any remaining invalid characters
        $channel_name = preg_replace('/[^a-z0-9\-]/', '', $channel_name);
        // Ensure it doesn't end with a hyphen
        $channel_name = rtrim($channel_name, '-');
        // Slack channel names have a 21 character limit, so truncate if needed
        if (strlen($channel_name) > 21) {
            $channel_name = substr($channel_name, 0, 21);
            $channel_name = rtrim($channel_name, '-'); // Remove trailing hyphen if truncation created one
        }
        
        //error_log("[DEBUG] Using email for channel: {$channel_name} (from email: {$email})");
        
    } else {
        // Fallback to session ID if no name or email found
        $channel_name = 'chat-' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $session_id));
        //error_log("[DEBUG] No name or email found, using session ID for channel: {$channel_name}");
    }
    
    // Final validation - ensure channel name meets Slack requirements
    if (strlen($channel_name) > 21) {
        $channel_name = substr($channel_name, 0, 21);
        $channel_name = rtrim($channel_name, '-');
    }
    
    //error_log("[DEBUG] Generated channel name: {$channel_name}");
    return $channel_name;
}

/**
 * Telegram Live Agent Handover
 * Creates a forum topic in the Telegram group and notifies agents
 */
public function mxchat_telegram_live_agent_handover($message, $user_id, $session_id) {
    // Check if Telegram agents are available
    $telegram_available = $this->options['telegram_status'] ?? 'off';
    if ($telegram_available !== 'on') {
        $away_message = $this->options['telegram_away_message'] ?? 'Sorry, live agents are currently unavailable. I can continue helping you as an AI assistant.';
        $this->fallbackResponse = [
            'text' => $away_message,
            'html' => '',
            'images' => [],
            'chat_mode' => 'ai'
        ];
        wp_send_json([
            'text' => $away_message,
            'html' => '',
            'chat_mode' => 'ai',
            'session_id' => $session_id
        ]);
        wp_die();
    }

    $telegram_bot_token = $this->options['telegram_bot_token'] ?? '';
    $telegram_group_id = $this->options['telegram_group_id'] ?? '';

    if (empty($telegram_bot_token) || empty($telegram_group_id)) {
        return false;
    }

    // Check if topic already exists for this session
    $topic_id = get_option("mxchat_telegram_topic_{$session_id}", '');

    if (empty($topic_id)) {
        // Generate topic name
        $topic_name = $this->generate_telegram_topic_name($session_id);

        // Random icon color (Telegram forum topic colors)
        $icon_colors = [0x6FB9F0, 0xFFD67E, 0xCB86DB, 0x8EEE98, 0xFF93B2, 0xFB6F5F];
        $icon_color = $icon_colors[array_rand($icon_colors)];

        // Create forum topic
        $response = wp_remote_post("https://api.telegram.org/bot{$telegram_bot_token}/createForumTopic", [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'chat_id' => $telegram_group_id,
                'name' => $topic_name,
                'icon_color' => $icon_color
            ])
        ]);

        if (!is_wp_error($response)) {
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);

            if (isset($response_data['ok']) && $response_data['ok']) {
                $topic_id = $response_data['result']['message_thread_id'];
                update_option("mxchat_telegram_topic_{$session_id}", $topic_id);
                update_option("mxchat_telegram_group_{$session_id}", $telegram_group_id);
            }
        }

        if (empty($topic_id)) {
            return false; // Failed to create topic
        }
    }

    // Get recent chat history
    $history = get_option("mxchat_history_{$session_id}", []);
    $recent_history = array_slice($history, -5);

    // Format conversation context for Telegram (HTML format)
    $conversation_context = "";
    if (!empty($recent_history)) {
        $conversation_context = "<b>Recent Conversation:</b>\n";
        foreach ($recent_history as $hist_message) {
            $role_display = $hist_message['role'] === 'user' ? '👤 User' : '🤖 AI';
            $escaped_content = htmlspecialchars($hist_message['content'], ENT_QUOTES, 'UTF-8');
            $conversation_context .= "{$role_display}: {$escaped_content}\n";
        }
        $conversation_context .= "\n";
    }

    // Get user info
    $user_email = get_option("mxchat_email_{$session_id}", 'Not provided');
    $user_name = get_option("mxchat_name_{$session_id}", 'Anonymous');

    // Update session mode
    update_option("mxchat_mode_{$session_id}", 'agent');

    // Send initial message to topic
    $escaped_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $topic_message = "🔔 <b>New Live Agent Request</b>\n\n";
    $topic_message .= "<b>Session ID:</b> <code>{$session_id}</code>\n";
    $topic_message .= "<b>User:</b> {$user_name}\n";
    $topic_message .= "<b>Email:</b> {$user_email}\n\n";

    if (!empty($conversation_context)) {
        $topic_message .= $conversation_context;
    }

    $topic_message .= "<b>Current Message:</b>\n{$escaped_message}\n\n";
    $topic_message .= "<i>Reply in this topic - messages will be sent to the user</i>\n";
    $topic_message .= "<i>Type #close, #end, #disconnect, or #done to end the session</i>";

    wp_remote_post("https://api.telegram.org/bot{$telegram_bot_token}/sendMessage", [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode([
            'chat_id' => $telegram_group_id,
            'message_thread_id' => $topic_id,
            'text' => $topic_message,
            'parse_mode' => 'HTML'
        ])
    ]);

    $success_message = $this->options['telegram_notification_message'] ?? "I've notified a support agent. Please allow a moment for them to respond.";
    $this->mxchat_save_chat_message($session_id, 'bot', $success_message);

    $this->fallbackResponse = [
        'text' => $success_message,
        'html' => '',
        'images' => [],
        'chat_mode' => 'agent'
    ];

    wp_send_json([
        'success' => true,
        'text' => $success_message,
        'html' => '',
        'chat_mode' => 'agent',
        'session_id' => $session_id,
        'fallbackResponse' => $this->fallbackResponse
    ]);
    wp_die();
}

/**
 * Generate topic name for Telegram forum
 */
private function generate_telegram_topic_name($session_id) {
    $name = null;
    $email = null;

    // Check logged in user
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        if (!empty($current_user->display_name)) {
            $name = $current_user->display_name;
        }
        if (!empty($current_user->user_email)) {
            $email = $current_user->user_email;
        }
    }

    // Check session data
    if (empty($name)) {
        $name = get_option("mxchat_name_{$session_id}");
    }
    if (empty($email)) {
        $email = get_option("mxchat_email_{$session_id}");
    }

    // Generate topic name
    $session_suffix = substr($session_id, -6);

    if (!empty($name)) {
        // Clean name for topic (max 128 chars in Telegram)
        $clean_name = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $name);
        $clean_name = trim($clean_name);
        if (strlen($clean_name) > 50) {
            $clean_name = substr($clean_name, 0, 50);
        }
        return "Chat - {$clean_name} ({$session_suffix})";
    } elseif (!empty($email)) {
        // Use email prefix
        $email_prefix = explode('@', $email)[0];
        if (strlen($email_prefix) > 30) {
            $email_prefix = substr($email_prefix, 0, 30);
        }
        return "Chat - {$email_prefix} ({$session_suffix})";
    }

    return "Chat - {$session_suffix}";
}

/**
 * Send user message to Telegram agent
 */
public function mxchat_send_user_message_to_telegram_agent($message, $user_id, $session_id) {
    $telegram_bot_token = $this->options['telegram_bot_token'] ?? '';
    $topic_id = get_option("mxchat_telegram_topic_{$session_id}", '');
    $group_id = get_option("mxchat_telegram_group_{$session_id}", '');

    if (empty($telegram_bot_token) || empty($topic_id) || empty($group_id)) {
        return false;
    }

    $escaped_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $user_message = "👤 <b>User:</b> {$escaped_message}";

    $response = wp_remote_post("https://api.telegram.org/bot{$telegram_bot_token}/sendMessage", [
        'headers' => ['Content-Type' => 'application/json'],
        'body' => json_encode([
            'chat_id' => $group_id,
            'message_thread_id' => $topic_id,
            'text' => $user_message,
            'parse_mode' => 'HTML'
        ])
    ]);

    return !is_wp_error($response);
}

/**
 * Handle incoming Telegram webhook
 */
public function handle_telegram_webhook(WP_REST_Request $request) {
    $body = $request->get_body();
    $data = json_decode($body, true);

    error_log('[MxChat Telegram DEBUG] Webhook received: ' . $body);

    // Handle message events from forum topics
    if (isset($data['message'])) {
        $message_data = $data['message'];

        // Skip if not from a forum topic
        if (!isset($message_data['message_thread_id'])) {
            error_log('[MxChat Telegram DEBUG] Skipped: No message_thread_id (not a forum topic message)');
            return new WP_REST_Response(['ok' => true]);
        }

        // Skip bot messages
        if (isset($message_data['from']['is_bot']) && $message_data['from']['is_bot']) {
            error_log('[MxChat Telegram DEBUG] Skipped: Message from bot');
            return new WP_REST_Response(['ok' => true]);
        }

        $chat_id = $message_data['chat']['id'] ?? '';
        $topic_id = $message_data['message_thread_id'];
        $message_text = $message_data['text'] ?? '';
        $message_id = $message_data['message_id'] ?? '';
        $from = $message_data['from'] ?? [];
        $agent_name = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
        if (empty($agent_name)) {
            $agent_name = $from['username'] ?? 'Agent';
        }

        error_log("[MxChat Telegram DEBUG] Parsed: chat_id={$chat_id}, topic_id={$topic_id}, agent={$agent_name}, text={$message_text}");

        // Skip empty messages
        if (empty($message_text)) {
            error_log('[MxChat Telegram DEBUG] Skipped: Empty message text');
            return new WP_REST_Response(['ok' => true]);
        }

        // Find session ID by topic ID - cast to string for comparison
        global $wpdb;
        $topic_id_str = strval($topic_id);
        $session_option = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options}
                 WHERE option_name LIKE %s
                 AND option_value = %s",
                'mxchat_telegram_topic_%',
                $topic_id_str
            )
        );

        error_log("[MxChat Telegram DEBUG] Looking for topic_id={$topic_id_str} in options, found: " . ($session_option ?: 'NULL'));

        if ($session_option) {
            $session_id = str_replace('mxchat_telegram_topic_', '', $session_option);
            error_log("[MxChat Telegram DEBUG] Session ID: {$session_id}");

            // Verify the group ID matches
            $stored_group_id = get_option("mxchat_telegram_group_{$session_id}", '');
            error_log("[MxChat Telegram DEBUG] Stored group_id={$stored_group_id}, received chat_id={$chat_id}");

            if (strval($stored_group_id) != strval($chat_id)) {
                error_log('[MxChat Telegram DEBUG] Skipped: Group ID mismatch');
                return new WP_REST_Response(['ok' => true]);
            }

            // Check for closure commands
            $lower_text = strtolower(trim($message_text));
            if (in_array($lower_text, ['#close', '#end', '#disconnect', '#done'])) {
                error_log("[MxChat Telegram DEBUG] Closure command received: {$lower_text}");
                // End the live agent session
                update_option("mxchat_mode_{$session_id}", 'ai');

                // Save disconnect message
                $disconnect_message = "Live agent session ended. You're now chatting with the AI assistant.";
                $this->mxchat_save_chat_message($session_id, 'bot', $disconnect_message);

                // Notify in Telegram
                $telegram_bot_token = $this->options['telegram_bot_token'] ?? '';
                if (!empty($telegram_bot_token)) {
                    wp_remote_post("https://api.telegram.org/bot{$telegram_bot_token}/sendMessage", [
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode([
                            'chat_id' => $chat_id,
                            'message_thread_id' => $topic_id,
                            'text' => "✅ Session closed. User returned to AI chatbot.",
                            'parse_mode' => 'HTML'
                        ])
                    ]);

                    // Optionally close the topic
                    wp_remote_post("https://api.telegram.org/bot{$telegram_bot_token}/closeForumTopic", [
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode([
                            'chat_id' => $chat_id,
                            'message_thread_id' => $topic_id
                        ])
                    ]);
                }

                return new WP_REST_Response(['ok' => true]);
            }

            // Deduplicate messages
            $message_key = md5($session_id . $message_id . $message_text);
            $processed_messages = get_transient('mxchat_telegram_messages_' . $session_id) ?: [];

            if (in_array($message_key, $processed_messages)) {
                error_log('[MxChat Telegram DEBUG] Skipped: Duplicate message');
                return new WP_REST_Response(['ok' => true]);
            }

            $processed_messages[] = $message_key;
            if (count($processed_messages) > 50) {
                $processed_messages = array_slice($processed_messages, -50);
            }
            set_transient('mxchat_telegram_messages_' . $session_id, $processed_messages, HOUR_IN_SECONDS);

            // Save the agent message - format with agent name prefix for proper parsing
            $formatted_message = "Agent: {$agent_name} - {$message_text}";
            error_log("[MxChat Telegram DEBUG] Saving agent message: {$formatted_message}");

            $this->mxchat_save_chat_message($session_id, 'agent', $formatted_message);

            // Verify the message was saved to history
            $history = get_option("mxchat_history_{$session_id}", []);
            $last_message = end($history);
            error_log("[MxChat Telegram DEBUG] History after save - count: " . count($history) . ", last message role: " . ($last_message['role'] ?? 'none'));

            // Send confirmation back to Telegram
            $telegram_bot_token = $this->options['telegram_bot_token'] ?? '';
            if (!empty($telegram_bot_token)) {
                $confirm_key = 'mxchat_telegram_confirm_' . $message_key;
                if (!get_transient($confirm_key)) {
                    wp_remote_post("https://api.telegram.org/bot{$telegram_bot_token}/sendMessage", [
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode([
                            'chat_id' => $chat_id,
                            'message_thread_id' => $topic_id,
                            'text' => "✅ <i>Message sent to user</i>",
                            'parse_mode' => 'HTML',
                            'reply_to_message_id' => $message_id
                        ])
                    ]);
                    set_transient($confirm_key, true, 300);
                }
            }
        } else {
            error_log("[MxChat Telegram DEBUG] No session found for topic_id={$topic_id}");
        }
    } else {
        error_log('[MxChat Telegram DEBUG] No message in webhook data');
    }

    return new WP_REST_Response(['ok' => true]);
}

public function mxchat_send_user_message_to_agent($message, $user_id, $session_id) {
    $slack_bot_token = $this->options['live_agent_bot_token'] ?? '';
    $channel_id = get_option("mxchat_channel_{$session_id}", '');

    if (empty($slack_bot_token) || empty($channel_id)) {
        return false;
    }

    $user_message = "ðŸ’¬ *User:* {$message}";

    $response = wp_remote_post('https://slack.com/api/chat.postMessage', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $slack_bot_token
        ],
        'body' => json_encode([
            'channel' => $channel_id,
            'text' => $user_message,
            'mrkdwn' => true
        ])
    ]);

    return !is_wp_error($response);
}
public function handle_slack_interaction(WP_REST_Request $request) {
    //error_log('Received Slack interaction');

    $payload = json_decode($request->get_param('payload'), true);
    //error_log('Payload: ' . print_r($payload, true));

    // Handle button click
    if ($payload['type'] === 'block_actions' && $payload['actions'][0]['action_id'] === 'reply_to_user') {
        $session_id = $payload['actions'][0]['value'];
        $trigger_id = $payload['trigger_id'];

        // Get Bot Token from settings
        $slack_token = $this->options['live_agent_bot_token'] ?? '';

        if (empty($slack_token)) {
            //error_log('Slack Bot Token not configured');
            return new WP_REST_Response(['error' => esc_html__('Bot token not configured', 'mxchat')], 400);
        }
        $response = wp_remote_post('https://slack.com/api/views.open', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $slack_token
            ],
            'body' => json_encode([
                'trigger_id' => $trigger_id,
                'view' => [
                    'type' => 'modal',
                    'callback_id' => 'reply_modal',
                    'title' => [
                        'type' => 'plain_text',
                        'text' => __('Reply to User', 'mxchat')
                    ],
                    'submit' => [
                        'type' => 'plain_text',
                        'text' => __('Send', 'mxchat')
                    ],
                    'close' => [
                        'type' => 'plain_text',
                        'text' => __('Cancel', 'mxchat')
                    ],
                    'blocks' => [
                        [
                            'type' => 'input',
                            'block_id' => 'reply_block',
                            'label' => [
                                'type' => 'plain_text',
                                'text' => sprintf(__('Reply to session: %s', 'mxchat'), $session_id)
                            ],
                            'element' => [
                                'type' => 'plain_text_input',
                                'action_id' => 'message',
                                'multiline' => true,
                                'placeholder' => [
                                    'type' => 'plain_text',
                                    'text' => __('Type your message here...', 'mxchat')
                                ]
                            ]
                        ]
                    ],
                    'private_metadata' => $session_id
                ]
            ])
        ]);

        //error_log('Views.open response: ' . print_r($response, true));

        // Return immediate acknowledgment
        return new WP_REST_Response(['ok' => true]);
    }

    // Handle modal submission
// Handle modal submission
if ($payload['type'] === 'view_submission') {
    $session_id = $payload['view']['private_metadata'];
    $message = $payload['view']['state']['values']['reply_block']['message']['value'];

    // Save the message (keep the message_id but don't include in response)
    $this->mxchat_save_chat_message($session_id, 'agent', $message);

    // Keep the original response format for Slack
    return new WP_REST_Response([
        'response_action' => 'clear'
    ]);
}

    // Default acknowledgment
    return new WP_REST_Response(['ok' => true]);
}
public function mxchat_handle_agent_response(WP_REST_Request $request) {
    //error_log('Received agent response request');
    //error_log('Request data: ' . print_r($request->get_params(), true));
   // //error_log('Raw body: ' . file_get_contents('php://input'));

    // Get the data from Slack's slash command format
    $command_text = $request->get_param('text');
   // //error_log('Command text: ' . $command_text);

    if (empty($command_text)) {
        //error_log(esc_html__('Agent response error: No command text received', 'mxchat'));
        return new WP_REST_Response([
            'error' => esc_html__('Command text is required. Format: /reply session_id message', 'mxchat')
        ], 400);
    }

    // Split the command text into session_id and message
    $parts = explode(' ', $command_text, 2);
    if (count($parts) !== 2) {
        //error_log('Agent response error: Invalid command format');
        return new WP_REST_Response([
            'error' => esc_html__('Invalid format. Use: /reply session_id message', 'mxchat')
        ], 400);
    }

    $session_id = sanitize_text_field($parts[0]);
    $message = sanitize_text_field($parts[1]);

    //error_log("Processing agent response - Session ID: $session_id, Message: $message");

    // Save the message
    $message_id = $this->mxchat_save_chat_message($session_id, 'agent', $message);

    if (!$message_id) {
       // //error_log('Failed to save agent message');
        return new WP_REST_Response([
            'error' => esc_html__('Failed to save message', 'mxchat')
        ], 500);
    }

    // Return success response in Slack's expected format
    return new WP_REST_Response([
        'response_type' => 'in_channel',
        'text' => esc_html__("Message sent successfully to session $session_id", 'mxchat')
    ], 200);
}
public function mxchat_handle_switch_to_chatbot_intent($message, $user_id, $session_id) {
    // Update mode to AI
    update_option("mxchat_mode_{$session_id}", 'ai');
    
    // Clear any existing PDF context to start fresh
    $this->clear_pdf_transients($session_id);
    
    // Set the response with explicit chat_mode
    $this->fallbackResponse = [
        'text' => esc_html__('You are now chatting with the AI chatbot.', 'mxchat'),
        'html' => '',
        'images' => [],
        'chat_mode' => 'ai' // Ensure this is set
    ];
    
    // Return the complete response array instead of just true
    return $this->fallbackResponse;
}

public function handle_slack_messages(WP_REST_Request $request) {
    // Log the incoming request for debugging
    //error_log('Slack events request received: ' . $request->get_body());
    
    $body = $request->get_body();
    $data = json_decode($body, true);
    
    // Handle Slack URL verification
    if (isset($data['type']) && $data['type'] === 'url_verification') {
        //error_log('Slack URL verification challenge: ' . $data['challenge']);
        return new WP_REST_Response($data['challenge'], 200, ['Content-Type' => 'text/plain']);
    }
    
    // IMPORTANT: Handle Slack's event deduplication
    if (isset($data['event_id'])) {
        $event_id = $data['event_id'];
        $processed_events = get_transient('mxchat_slack_events') ?: [];
        
        // Check if we've already processed this event
        if (in_array($event_id, $processed_events)) {
            //error_log("Duplicate event detected: $event_id");
            return new WP_REST_Response(['ok' => true]);
        }
        
        // Add this event to processed list
        $processed_events[] = $event_id;
        // Keep only last 100 events to prevent memory issues
        if (count($processed_events) > 100) {
            $processed_events = array_slice($processed_events, -100);
        }
        // Store for 1 hour
        set_transient('mxchat_slack_events', $processed_events, HOUR_IN_SECONDS);
    }
    
    // Handle message events
    if (isset($data['event']) && $data['event']['type'] === 'message') {
        $event = $data['event'];
        
        // Skip bot messages and messages with subtypes (like bot_message)
        if (isset($event['bot_id']) || isset($event['subtype'])) {
            return new WP_REST_Response(['ok' => true]);
        }
        
        // Additional check: Skip if this is a threaded reply to our confirmation
        if (isset($event['thread_ts']) && $event['thread_ts'] !== $event['ts']) {
            return new WP_REST_Response(['ok' => true]);
        }
        
        $channel_id = $event['channel'];
        $message_text = $event['text'] ?? '';
        $message_ts = $event['ts'] ?? '';
        
        // Find session ID by looking for matching channel
        global $wpdb;
        $session_option = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE 'mxchat_channel_%' 
                 AND option_value = %s",
                $channel_id
            )
        );
        
        if ($session_option) {
            $session_id = str_replace('mxchat_channel_', '', $session_option);
            
            // Create a unique key for this specific message
            $message_key = md5($session_id . $message_ts . $message_text);
            $processed_messages = get_transient('mxchat_processed_messages_' . $session_id) ?: [];
            
            // Check if we've already processed this exact message
            if (in_array($message_key, $processed_messages)) {
                //error_log("Duplicate message detected for session $session_id");
                return new WP_REST_Response(['ok' => true]);
            }
            
            // Add to processed messages
            $processed_messages[] = $message_key;
            // Keep only last 50 messages per session
            if (count($processed_messages) > 50) {
                $processed_messages = array_slice($processed_messages, -50);
            }
            set_transient('mxchat_processed_messages_' . $session_id, $processed_messages, HOUR_IN_SECONDS);
            
            // Save the agent message
            $this->mxchat_save_chat_message($session_id, 'agent', $message_text);
            
            // Send confirmation back to Slack (only once)
            $slack_bot_token = $this->options['live_agent_bot_token'] ?? '';
            if (!empty($slack_bot_token)) {
                // Use a transient to prevent duplicate confirmations
                $confirm_key = 'mxchat_confirm_' . $message_key;
                if (!get_transient($confirm_key)) {
                    wp_remote_post('https://slack.com/api/chat.postMessage', [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer ' . $slack_bot_token
                        ],
                        'body' => json_encode([
                            'channel' => $channel_id,
                            'text' => "âœ… _Message sent to user_",
                            'thread_ts' => $event['ts'] // Reply in thread
                        ])
                    ]);
                    // Set transient to prevent duplicate confirmations
                    set_transient($confirm_key, true, 300); // 5 minutes
                }
            }
        }
    }
    
    return new WP_REST_Response(['ok' => true]);
}

// For the word upload handler
public function mxchat_handle_word_upload() {
    // Delegate to word handler
    $this->word_handler->mxchat_handle_word_upload();
}

// For the word removal handler
public function mxchat_handle_word_remove() {
    // Delegate to word handler
    $this->word_handler->mxchat_handle_word_remove();
}

// For the word status check
public function mxchat_check_word_status() {
    // Delegate to word handler
    $this->word_handler->mxchat_check_word_status();
}


private function mxchat_get_user_identifier() {
    return MxChat_User::mxchat_get_user_identifier();
}

private function mxchat_generate_embedding($text, $api_key) {
    try {
        // Get options and selected model
        $options = get_option('mxchat_options');
        $selected_model = $options['embedding_model'] ?? 'text-embedding-ada-002';
        
        // Determine endpoint and API key based on model
        if (strpos($selected_model, 'voyage') === 0) {
            $endpoint = 'https://api.voyageai.com/v1/embeddings';
            $api_key = $options['voyage_api_key'] ?? '';
            
            // Check if Voyage API key is missing
            if (empty($api_key)) {
                //error_log('Voyage API key is missing');
                return [
                    'error' => esc_html__('Voyage AI API key is not configured', 'mxchat'),
                    'error_code' => 'missing_voyage_api_key'
                ];
            }
        } elseif (strpos($selected_model, 'gemini-embedding') === 0) {
            $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $selected_model . ':embedContent';
            $api_key = $options['gemini_api_key'] ?? '';
            
            // Check if Gemini API key is missing
            if (empty($api_key)) {
                //error_log('Gemini API key is missing');
                return [
                    'error' => esc_html__('Google Gemini API key is not configured', 'mxchat'),
                    'error_code' => 'missing_gemini_api_key'
                ];
            }
        } else {
            $endpoint = 'https://api.openai.com/v1/embeddings';
            // Use the passed API key for OpenAI
            
            // Check if OpenAI API key is missing
            if (empty($api_key)) {
                //error_log('OpenAI API key is missing');
                return [
                    'error' => esc_html__('OpenAI API key is not configured', 'mxchat'),
                    'error_code' => 'missing_openai_api_key'
                ];
            }
        }
        
        // Check if text is empty
        if (empty($text)) {
            //error_log('Empty text provided for embedding generation');
            return [
                'error' => esc_html__('No text provided for embedding generation', 'mxchat'),
                'error_code' => 'empty_embedding_text'
            ];
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
        
        // Prepare request arguments
        $args = [
            'body' => wp_json_encode($request_body),
            'headers' => $headers,
            'timeout' => 60,
            'redirection' => 5,
            'blocking' => true,
            'httpversion' => '1.0',
            'sslverify' => true,
        ];
        
        // Make the request
        $response = wp_remote_post($endpoint, $args);
        
        // Handle WordPress errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            //error_log('Embedding Generation Error: ' . $error_message);
            return [
                'error' => esc_html__('Connection error when generating embeddings: ', 'mxchat') . esc_html($error_message),
                'error_code' => 'embedding_connection_error'
            ];
        }
        
        // Check HTTP status code
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            
            $error_message = isset($response_body['error']['message']) 
                ? $response_body['error']['message'] 
                : 'HTTP Error ' . $status_code;
                
            $error_type = isset($response_body['error']['type']) 
                ? $response_body['error']['type'] 
                : 'unknown';
                
            //error_log('Embedding API HTTP Error: ' . $status_code . ' - ' . $error_message);
            
            // Handle specific error types
            switch ($error_type) {
                case 'invalid_request_error':
                    if (strpos($error_message, 'API key') !== false) {
                        return [
                            'error' => esc_html__('Invalid API key for embedding generation. Please check your API key configuration.', 'mxchat'),
                            'error_code' => 'embedding_invalid_api_key'
                        ];
                    }
                    break;
                    
                case 'authentication_error':
                    return [
                        'error' => esc_html__('Authentication failed for embedding generation. Please check your API key.', 'mxchat'),
                        'error_code' => 'embedding_auth_error'
                    ];
                    
                case 'rate_limit_exceeded':
                    return [
                        'error' => esc_html__('Rate limit exceeded for embedding generation. Please try again later.', 'mxchat'),
                        'error_code' => 'embedding_rate_limit'
                    ];
                    
                case 'quota_exceeded':
                    return [
                        'error' => esc_html__('API quota exceeded for embedding generation. Please check your billing details.', 'mxchat'),
                        'error_code' => 'embedding_quota_exceeded'
                    ];
            }
            
            // Generic error fallback
            return [
                'error' => esc_html__('Embedding API error - check embedding API key.: ', 'mxchat') . esc_html($error_message),
                'error_code' => 'embedding_api_error',
                'status_code' => $status_code
            ];
        }
        
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Handle different response formats based on provider
        if (strpos($selected_model, 'gemini-embedding') === 0) {
            // Gemini API response format
            if (isset($response_body['embedding']['values']) && is_array($response_body['embedding']['values'])) {
                return $response_body['embedding']['values'];
            } else {
                //error_log('Invalid Gemini embedding response: ' . wp_json_encode($response_body));
                return [
                    'error' => esc_html__('Received invalid embedding data from the Gemini API.', 'mxchat'),
                    'error_code' => 'invalid_gemini_embedding_response'
                ];
            }
        } else {
            // OpenAI/Voyage API response format
            if (isset($response_body['data'][0]['embedding']) && is_array($response_body['data'][0]['embedding'])) {
                return $response_body['data'][0]['embedding'];
            } else {
                //error_log('Invalid embedding response: ' . wp_json_encode($response_body));
                return [
                    'error' => esc_html__('Received invalid embedding data from the API.', 'mxchat'),
                    'error_code' => 'invalid_embedding_response'
                ];
            }
        }
    } catch (Exception $e) {
        //error_log('Embedding Exception: ' . $e->getMessage());
        return [
            'error' => esc_html__('System error when generating embeddings: ', 'mxchat') . esc_html($e->getMessage()),
            'error_code' => 'embedding_exception'
        ];
    }
}


private function mxchat_find_relevant_content($user_embedding, $bot_id = 'default', $user_query = '') {
    error_log("MXCHAT DEBUG: find_relevant_content called with bot_id: " . $bot_id);

    // Check for OpenAI Vector Store first (takes priority when enabled)
    $bot_vectorstore_config = $this->get_bot_vectorstore_config($bot_id);

    if ($bot_vectorstore_config['use_vectorstore']) {
        // Get current model to verify it's an OpenAI model
        $bot_options = $this->get_bot_options($bot_id);
        $mxchat_options = get_option('mxchat_options', array());
        $current_options = !empty($bot_options) ? $bot_options : $mxchat_options;
        $selected_model = $current_options['model'] ?? 'gpt-5.1-chat-latest';

        if ($this->is_openai_chat_model($selected_model)) {
            error_log("MXCHAT DEBUG: Using OpenAI Vector Store for knowledge retrieval");
            return $this->find_relevant_content_openai_vectorstore($user_query, $bot_id, $bot_vectorstore_config);
        } else {
            error_log("MXCHAT DEBUG: Vector Store enabled but model is not OpenAI (" . $selected_model . "), skipping Vector Store");
        }
    }

    // Get bot-specific Pinecone configuration
    $bot_pinecone_config = $this->get_bot_pinecone_config($bot_id);

    // Debug: Log the Pinecone configuration
    error_log("MXCHAT DEBUG: Pinecone config for bot '$bot_id':");
    error_log("  - use_pinecone: " . ($bot_pinecone_config['use_pinecone'] ? 'true' : 'false'));
    error_log("  - api_key: " . (empty($bot_pinecone_config['api_key']) ? 'EMPTY' : 'SET (hidden)'));
    error_log("  - host: " . ($bot_pinecone_config['host'] ?? 'NOT SET'));
    error_log("  - namespace: " . ($bot_pinecone_config['namespace'] ?? 'NOT SET'));

    // Determine whether to use Pinecone based on bot configuration
    $use_pinecone = isset($bot_pinecone_config['use_pinecone']) ? $bot_pinecone_config['use_pinecone'] : false;

    error_log("MXCHAT DEBUG: Using " . ($use_pinecone ? "Pinecone" : "WordPress Database") . " for knowledge retrieval");

    if ($use_pinecone) {
        return $this->find_relevant_content_pinecone($user_embedding, $bot_id, $bot_pinecone_config);
    } else {
        return $this->find_relevant_content_wordpress($user_embedding, $bot_id);
    }
}

private function find_relevant_content_wordpress($user_embedding, $bot_id = 'default') {
    global $wpdb;
    $system_prompt_table = $wpdb->prefix . 'mxchat_system_prompt_content';
    $cache_key = 'mxchat_system_prompt_embeddings_' . $bot_id;
    $batch_size = 500;

    // Initialize similarity analysis storage
    $this->last_similarity_analysis = [
        'knowledge_base_type' => 'WordPress Database',
        'bot_id' => $bot_id,
        'top_matches' => [],
        'threshold_used' => 0,
        'total_checked' => 0
    ];

    // NEW: Initialize valid URLs array
    $valid_urls = [];

    // Get bot-specific options for similarity threshold
    $bot_options = $this->get_bot_options($bot_id);
    $current_options = !empty($bot_options) ? $bot_options : $this->options;

    // Retrieve embeddings from cache or database
    $embeddings = wp_cache_get($cache_key, 'mxchat_system_prompts');
    if ($embeddings === false) {
        // Cache miss - load embeddings from database WITH CONTENT and ROLE RESTRICTION for testing
        $embeddings = [];
        $offset = 0;

        do {
            // Add bot_id filter if not default and if bot_metadata column exists
            $bot_filter = '';
            if ($bot_id !== 'default') {
                // Check if bot_metadata column exists
                $column_exists = $wpdb->get_var("SHOW COLUMNS FROM {$system_prompt_table} LIKE 'bot_metadata'");
                if ($column_exists) {
                    $bot_filter = $wpdb->prepare(" AND (bot_metadata = %s OR bot_metadata IS NULL OR bot_metadata = '')", $bot_id);
                }
            }

            $query = $wpdb->prepare(
                "SELECT id, embedding_vector, article_content, source_url, role_restriction
                FROM {$system_prompt_table}
                WHERE 1=1 {$bot_filter}
                LIMIT %d OFFSET %d",
                $batch_size,
                $offset
            );

            $batch = $wpdb->get_results($query);
            if (empty($batch)) {
                break;
            }

            $embeddings = array_merge($embeddings, $batch);
            $offset += $batch_size;
            unset($batch);
        } while (true);

        if (empty($embeddings)) {
            // Store empty array for valid URLs since no content found
            $this->current_valid_urls = [];
            return '';
        }
        
        // Cache embeddings for future use (but note: this now includes content and role restrictions)
        wp_cache_set($cache_key, $embeddings, 'mxchat_system_prompts', 3600);
    }

    // Get knowledge manager instance for role checking
    $knowledge_manager = MxChat_Knowledge_Manager::get_instance();

    // Get base similarity threshold from bot options or default options
    $similarity_threshold = isset($current_options['similarity_threshold']) 
        ? ((int) $current_options['similarity_threshold']) / 100 
        : 0.35;
        
    $this->last_similarity_analysis['threshold_used'] = $similarity_threshold;
    
    // Calculate similarities and build results array
    $all_similarities = [];
    $url_groups = array(); // NEW: Group by source_url for chunk reassembly

    foreach ($embeddings as $embedding) {
        $database_embedding = $embedding->embedding_vector
            ? unserialize($embedding->embedding_vector, ['allowed_classes' => false])
            : null;

        if (is_array($database_embedding) && is_array($user_embedding)) {
            $similarity = $this->mxchat_calculate_cosine_similarity($user_embedding, $database_embedding);

            // Check role access
            $role_restriction = $embedding->role_restriction ?? 'public';
            $has_access = $knowledge_manager->mxchat_user_has_content_access($role_restriction);

            // Store ALL similarities for testing (top 10)
            $source_display = '';
            $source_url = $embedding->source_url ?? '';
            if (!empty($source_url) && $source_url !== '#') {
                $source_display = $source_url;
            } else {
                $content_preview = strip_tags($embedding->article_content ?? '');
                $content_preview = preg_replace('/\s+/', ' ', $content_preview);
                $source_display = substr(trim($content_preview), 0, 50) . '...';
            }

            // Parse chunk metadata for display
            $article_content_for_parse = $embedding->article_content ?? '';
            $parsed_for_display = MxChat_Chunker::parse_stored_chunk($article_content_for_parse);
            $is_chunk = $parsed_for_display['is_chunked'];
            $chunk_meta = $parsed_for_display['metadata'];

            $all_similarities[] = [
                'document_id' => $embedding->id,
                'similarity' => $similarity,
                'similarity_percentage' => round($similarity * 100, 2),
                'above_threshold' => $similarity >= $similarity_threshold,
                'source_display' => $source_display,
                'content_preview' => substr(strip_tags($parsed_for_display['text'] ?? ''), 0, 100) . '...',
                'used_for_context' => false,
                'role_restriction' => $role_restriction,
                'has_access' => $has_access,
                'filtered_out' => !$has_access,
                'is_chunk' => $is_chunk,
                'chunk_index' => $is_chunk ? ($chunk_meta['chunk_index'] ?? 0) : null,
                'total_chunks' => $is_chunk ? ($chunk_meta['total_chunks'] ?? 1) : null
            ];

            // Only consider results above threshold AND with access for content retrieval
            if ($similarity >= $similarity_threshold && $has_access && !empty($source_url)) {
                // Parse chunk metadata if present
                $article_content = $embedding->article_content ?? '';
                $parsed = MxChat_Chunker::parse_stored_chunk($article_content);
                $is_chunked = $parsed['is_chunked'];
                $chunk_index = $parsed['metadata']['chunk_index'] ?? 0;
                $text_content = $parsed['text'];

                // Group by source URL
                if (!isset($url_groups[$source_url])) {
                    $url_groups[$source_url] = array(
                        'source_url' => $source_url,
                        'best_score' => 0,
                        'is_chunked' => $is_chunked,
                        'chunks' => array(),
                        'single_text' => '',
                        'single_id' => null
                    );
                }

                // Track best score for this URL
                if ($similarity > $url_groups[$source_url]['best_score']) {
                    $url_groups[$source_url]['best_score'] = $similarity;
                }

                // Store chunk info or single text
                if ($is_chunked) {
                    $url_groups[$source_url]['is_chunked'] = true;
                    $url_groups[$source_url]['chunks'][] = array(
                        'id' => $embedding->id,
                        'score' => $similarity,
                        'chunk_index' => $chunk_index,
                        'text' => $text_content
                    );
                } else {
                    $url_groups[$source_url]['single_text'] = $text_content;
                    $url_groups[$source_url]['single_id'] = $embedding->id;
                }
            }
        }

        unset($database_embedding);
    }

    // Sort ALL similarities for testing display (highest first)
    usort($all_similarities, function ($a, $b) {
        return $b['similarity'] <=> $a['similarity'];
    });

    // Sort URL groups by best score (highest first)
    uasort($url_groups, function($a, $b) {
        return $b['best_score'] <=> $a['best_score'];
    });

    // Get RAG sources limit from options (default 6, min 3, max 10)
    $rag_sources_limit = isset($options['rag_sources_limit']) ? intval($options['rag_sources_limit']) : 6;
    if ($rag_sources_limit < 3) $rag_sources_limit = 3;
    if ($rag_sources_limit > 10) $rag_sources_limit = 10;

    // Take top N unique URLs based on user setting
    $top_urls = array_slice($url_groups, 0, $rag_sources_limit, true);

    // Track which document IDs are used for context
    $used_document_ids = [];
    foreach ($top_urls as $group) {
        if ($group['is_chunked']) {
            foreach ($group['chunks'] as $chunk) {
                $used_document_ids[] = $chunk['id'];
            }
        } elseif ($group['single_id']) {
            $used_document_ids[] = $group['single_id'];
        }
    }

    // Update the all_similarities array to mark which were actually used
    foreach ($all_similarities as &$similarity_item) {
        $similarity_item['used_for_context'] = in_array($similarity_item['document_id'], $used_document_ids);
    }

    // Store top 10 for testing panel
    $this->last_similarity_analysis['top_matches'] = array_slice($all_similarities, 0, 10);
    $this->last_similarity_analysis['total_checked'] = count($embeddings);

    // Initialize final content
    $content = '';
    $matches_used = 0;
    $total_chunks_used = 0;
    $max_total_chunks = 30; // Hard cap on total chunks to prevent excessive token usage

    // Check if citation links are enabled (default to 'on' for backwards compatibility)
    // Use fresh options to ensure we get the latest setting value
    $fresh_options = get_option('mxchat_options', []);
    $citation_links_enabled = isset($fresh_options['citation_links_toggle']) ? ($fresh_options['citation_links_toggle'] === 'on') : true;

    // Build content from top URLs
    foreach ($top_urls as $source_url => $group) {
        // Stop if we've hit the total chunk limit
        if ($total_chunks_used >= $max_total_chunks) {
            break;
        }

        $full_text = '';
        $chunks_in_this_source = 1; // Default for non-chunked content

        if ($group['is_chunked']) {
            // Calculate how many chunks we can still use
            $chunks_remaining = $max_total_chunks - $total_chunks_used;

            // Fetch chunks for this URL with limit
            $full_text = $this->reassemble_chunks_from_wordpress($source_url, $chunks_remaining, $chunks_in_this_source);

            // If fetching all chunks fails, fall back to matched chunks
            if (empty($full_text)) {
                // Sort matched chunks by index and concatenate
                usort($group['chunks'], function($a, $b) {
                    return $a['chunk_index'] <=> $b['chunk_index'];
                });

                $chunk_texts = array();
                $chunks_in_this_source = 0;
                foreach ($group['chunks'] as $chunk) {
                    if ($total_chunks_used + $chunks_in_this_source >= $max_total_chunks) {
                        break;
                    }
                    $chunk_texts[] = $chunk['text'];
                    $chunks_in_this_source++;
                }
                $full_text = implode("\n\n", $chunk_texts);
            }
        } else {
            $full_text = $group['single_text'];
            $chunks_in_this_source = 1;
        }

        if (!empty($full_text)) {
            // Strip URLs from content if citation links are disabled
            if (!$citation_links_enabled) {
                $full_text = preg_replace('#\bhttps?://[^\s<>"\']+#i', '', $full_text);
                $full_text = preg_replace('/\s+/', ' ', trim($full_text)); // Clean up extra spaces
            }

            $content .= "## Reference " . ($matches_used + 1) . " ##\n";
            $content .= $full_text . "\n\n";

            // Only include citation URLs if citation links are enabled
            if ($citation_links_enabled && !empty($source_url) && $source_url !== '#') {
                $valid_urls[] = $source_url;
                $content .= "URL: " . $source_url . "\n\n";
            }

            // Extract any URLs from the text content itself (only if citation links enabled)
            if ($citation_links_enabled) {
                preg_match_all(
                    '#\bhttps?://[^\s<>"\']+#i',
                    $full_text,
                    $content_urls
                );
                if (!empty($content_urls[0])) {
                    $valid_urls = array_merge($valid_urls, $content_urls[0]);
                }
            }

            $matches_used++;
            $total_chunks_used += $chunks_in_this_source;
        }
    }

    // NEW: Store unique valid URLs for validation
    $this->current_valid_urls = array_unique($valid_urls);

    // Add response guidelines
    if (empty($top_urls)) {
        $content = "No reference information was found for this query.\n\n";
    } else {
        // Build response guidelines based on citation links setting
        $content .= "\n## Response Guidelines ##\n" .
                   "You are an AI Chatbot. Answer naturally and helpfully using only the information from the references above. " .
                   "Be conversational and friendly, but never mention your knowledge base or training data. " .
                   "If you don't have specific information or are uncertain about any details, it's always " .
                   "better to honestly say you don't know rather than making up or guessing at answers. " .
                   "When information is incomplete, let them know you are unsure.\n\n";

        // Only add hyperlink instructions if citation links are enabled
        if ($citation_links_enabled) {
            $content .= "CRITICAL: When creating hyperlinks, always use proper markdown format with descriptive text: " .
                       "[descriptive text](url). NEVER use empty brackets like [](url). The text in brackets must describe what the link is about.";
        } else {
            $content .= "IMPORTANT: Do not include any citation links, source URLs, or hyperlinks in your responses. " .
                       "Simply provide helpful answers based on the reference information without citing sources.";
        }
    }

    return trim($content);
}

/**
 * Fetch and reassemble chunks for a URL from WordPress database
 *
 * @param string $source_url The source URL to fetch chunks for
 * @param int $max_chunks Maximum number of chunks to return (0 = unlimited)
 * @param int &$chunk_count Reference to store the actual number of chunks returned
 * @return string Reassembled content from chunks
 */
private function reassemble_chunks_from_wordpress($source_url, $max_chunks = 0, &$chunk_count = 0) {
    global $wpdb;
    $table = $wpdb->prefix . 'mxchat_system_prompt_content';

    // Fetch all rows with this source_url
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT article_content FROM {$table}
         WHERE source_url = %s
         ORDER BY id ASC",
        $source_url
    ));

    if (empty($rows)) {
        $chunk_count = 0;
        return '';
    }

    // Parse and sort chunks by index
    $chunks = array();
    foreach ($rows as $row) {
        $parsed = MxChat_Chunker::parse_stored_chunk($row->article_content);

        if ($parsed['is_chunked']) {
            $chunk_index = $parsed['metadata']['chunk_index'] ?? 0;
            $chunks[$chunk_index] = $parsed['text'];
        } else {
            // Non-chunked content - just return it
            $chunks[] = $parsed['text'];
        }
    }

    // Sort by chunk index
    ksort($chunks);

    // Apply chunk limit if specified
    if ($max_chunks > 0 && count($chunks) > $max_chunks) {
        $chunks = array_slice($chunks, 0, $max_chunks, true);
    }

    // Store actual chunk count
    $chunk_count = count($chunks);

    // Reassemble content
    return implode("\n\n", $chunks);
}

private function find_relevant_content_pinecone($user_embedding, $bot_id = 'default', $bot_config = null) {
    global $wpdb;
    
    error_log("MXCHAT DEBUG: find_relevant_content_pinecone called");
    error_log("  - bot_id: " . $bot_id);
    error_log("  - user_embedding is array: " . (is_array($user_embedding) ? 'yes' : 'no'));
    error_log("  - user_embedding count: " . (is_array($user_embedding) ? count($user_embedding) : 'N/A'));
    
    // Use bot-specific config or fall back to default
    if ($bot_config === null) {
        $bot_config = $this->get_bot_pinecone_config($bot_id);
    }
    
    $api_key = $bot_config['api_key'] ?? '';
    $host = $bot_config['host'] ?? '';
    $namespace = $bot_config['namespace'] ?? '';
    
    error_log("MXCHAT DEBUG: Pinecone query parameters:");
    error_log("  - API Key: " . (empty($api_key) ? 'EMPTY - ERROR!' : 'Present (length: ' . strlen($api_key) . ')'));
    error_log("  - Host: " . (empty($host) ? 'EMPTY - ERROR!' : $host));
    error_log("  - Namespace: " . (empty($namespace) ? 'EMPTY (will use default)' : $namespace));
    
    // Initialize similarity analysis storage
    $this->last_similarity_analysis = [
        'knowledge_base_type' => 'Pinecone',
        'bot_id' => $bot_id,
        'namespace' => $namespace,
        'top_matches' => [],
        'threshold_used' => 0,
        'total_checked' => 0
    ];
    
    // NEW: Initialize valid URLs array
    $valid_urls = [];
    
    if (empty($host) || empty($api_key)) {
        error_log("MXCHAT DEBUG ERROR: Missing Pinecone host or API key!");
        error_log("  - Host empty: " . (empty($host) ? 'YES' : 'NO'));
        error_log("  - API key empty: " . (empty($api_key) ? 'YES' : 'NO'));
        // Store empty array for valid URLs since we can't proceed
        $this->current_valid_urls = [];
        return '';
    }
    
    // Get knowledge manager instance for role checking
    $knowledge_manager = MxChat_Knowledge_Manager::get_instance();
    
    // Get the similarity threshold from the bot options or main options
    $bot_options = $this->get_bot_options($bot_id);
    $current_options = !empty($bot_options) ? $bot_options : get_option('mxchat_options', []);
    
    $similarity_threshold = isset($current_options['similarity_threshold']) 
        ? ((int) $current_options['similarity_threshold']) / 100 
        : 0.35;
    
    $this->last_similarity_analysis['threshold_used'] = $similarity_threshold;
    
    // Prepare the query request for Pinecone
    $api_endpoint = "https://{$host}/query";
    
    $request_body = array(
        'vector' => $user_embedding,
        'topK' => 50, // Increased for chunked content grouping - need more candidates to find top N unique URLs
        'includeMetadata' => true,
        'includeValues' => true
    );
    
    // Add namespace if specified for this bot
    if (!empty($namespace)) {
        $request_body['namespace'] = $namespace;
    }
    
    error_log("MXCHAT DEBUG: About to call Pinecone API");
    error_log("  - Endpoint: " . $api_endpoint);
    error_log("  - Namespace in request: " . (!empty($namespace) ? $namespace : 'NOT SET'));
    
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
        error_log("MXCHAT DEBUG ERROR: WP Error in Pinecone request: " . $response->get_error_message());
        // Store empty array for valid URLs
        $this->current_valid_urls = [];
        return '';
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    error_log("MXCHAT DEBUG: Pinecone response code: " . $response_code);
    
    if ($response_code !== 200) {
        $response_body = wp_remote_retrieve_body($response);
        error_log("MXCHAT DEBUG ERROR: Pinecone API error response: " . substr($response_body, 0, 500));
        // Store empty array for valid URLs
        $this->current_valid_urls = [];
        return '';
    }
    
    // ADD DETAILED DEBUG SECTION HERE
    $response_body = wp_remote_retrieve_body($response);
    error_log("MXCHAT DEBUG: Raw Pinecone response length: " . strlen($response_body));
    
    $results = json_decode($response_body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("MXCHAT DEBUG ERROR: JSON decode error: " . json_last_error_msg());
        error_log("MXCHAT DEBUG: First 500 chars of response: " . substr($response_body, 0, 500));
        // Store empty array for valid URLs
        $this->current_valid_urls = [];
        return '';
    }
    
    error_log("MXCHAT DEBUG: Pinecone response structure:");
    error_log("  - Has 'matches' key: " . (isset($results['matches']) ? 'yes' : 'no'));
    error_log("  - Has 'namespace' key: " . (isset($results['namespace']) ? 'yes (' . $results['namespace'] . ')' : 'no'));
    
    if (empty($results['matches'])) {
        error_log("MXCHAT DEBUG: No matches found in Pinecone response");
        error_log("MXCHAT DEBUG: Response keys: " . implode(', ', array_keys($results)));
        // Store empty array for valid URLs
        $this->current_valid_urls = [];
        return '';
    }
    
    error_log("MXCHAT DEBUG: Found " . count($results['matches']) . " matches in Pinecone");
    
    // Log first match details for debugging
    if (!empty($results['matches'][0])) {
        $first_match = $results['matches'][0];
        error_log("MXCHAT DEBUG: First match details:");
        error_log("  - Score: " . ($first_match['score'] ?? 'no score'));
        error_log("  - Has metadata: " . (isset($first_match['metadata']) ? 'yes' : 'no'));
        if (isset($first_match['metadata'])) {
            error_log("  - Metadata keys: " . implode(', ', array_keys($first_match['metadata'])));
        }
    }
    
    // Initialize the final content
    $content = '';
    $matches_used = 0;
    $matches_used_for_context = [];
    $total_chunks_used = 0;
    $max_total_chunks = 30; // Hard cap on total chunks to prevent excessive token usage

    // Check if citation links are enabled (default to 'on' for backwards compatibility)
    // Use fresh options to ensure we get the latest setting value
    $fresh_options = get_option('mxchat_options', []);
    $citation_links_enabled = isset($fresh_options['citation_links_toggle']) ? ($fresh_options['citation_links_toggle'] === 'on') : true;

    // NEW CHUNKING LOGIC: Group results by source_url for chunk reassembly
    $url_groups = array();

    foreach ($results['matches'] as $index => $match) {
        // Skip if similarity is below threshold
        if ($match['score'] < $similarity_threshold) {
            continue;
        }

        $metadata = $match['metadata'] ?? array();
        $source_url = $metadata['source_url'] ?? '';

        if (empty($source_url)) {
            continue;
        }

        // LAZY ROLE CHECK: Only check role for content we're actually considering
        $match_id = $match['id'] ?? '';
        $role_restriction = $this->get_single_vector_role($match_id, $metadata);
        $has_access = $knowledge_manager->mxchat_user_has_content_access($role_restriction);

        // Skip if user doesn't have access
        if (!$has_access) {
            continue;
        }

        // Group by source URL
        if (!isset($url_groups[$source_url])) {
            $url_groups[$source_url] = array(
                'source_url' => $source_url,
                'best_score' => 0,
                'is_chunked' => isset($metadata['is_chunked']) && $metadata['is_chunked'],
                'chunks' => array(),
                'single_text' => ''
            );
        }

        // Track best score for this URL
        if ($match['score'] > $url_groups[$source_url]['best_score']) {
            $url_groups[$source_url]['best_score'] = $match['score'];
        }

        // Store chunk info or single text
        if ($url_groups[$source_url]['is_chunked']) {
            $url_groups[$source_url]['chunks'][] = array(
                'id' => $match_id,
                'score' => $match['score'],
                'chunk_index' => $metadata['chunk_index'] ?? 0,
                'text' => $metadata['text'] ?? ''
            );
        } else {
            // Non-chunked content - just store the text
            $url_groups[$source_url]['single_text'] = $metadata['text'] ?? '';
            $url_groups[$source_url]['single_id'] = $match_id;
        }
    }

    // Sort URL groups by best score (highest first)
    uasort($url_groups, function($a, $b) {
        return $b['best_score'] <=> $a['best_score'];
    });

    // Get RAG sources limit from options (default 6, min 3, max 10)
    $rag_sources_limit = isset($current_options['rag_sources_limit']) ? intval($current_options['rag_sources_limit']) : 6;
    if ($rag_sources_limit < 3) $rag_sources_limit = 3;
    if ($rag_sources_limit > 10) $rag_sources_limit = 10;

    // Take top N unique URLs based on user setting
    $top_urls = array_slice($url_groups, 0, $rag_sources_limit, true);

    // Track which match IDs are actually used for context
    foreach ($top_urls as $group) {
        if ($group['is_chunked']) {
            foreach ($group['chunks'] as $chunk) {
                $matches_used_for_context[] = $chunk['id'];
            }
        } elseif (!empty($group['single_id'])) {
            $matches_used_for_context[] = $group['single_id'];
        }
    }

    // Build content from top URLs
    foreach ($top_urls as $source_url => $group) {
        // Stop if we've hit the total chunk limit
        if ($total_chunks_used >= $max_total_chunks) {
            break;
        }

        $full_text = '';
        $chunks_in_this_source = 1; // Default for non-chunked content

        if ($group['is_chunked']) {
            // Calculate how many chunks we can still use
            $chunks_remaining = $max_total_chunks - $total_chunks_used;

            // Fetch chunks for this URL with limit
            $full_text = $this->reassemble_chunks_from_pinecone($source_url, $bot_config, $chunks_remaining, $chunks_in_this_source);

            // If fetching all chunks fails, fall back to matched chunks
            if (empty($full_text)) {
                // Sort matched chunks by index and concatenate
                usort($group['chunks'], function($a, $b) {
                    return $a['chunk_index'] <=> $b['chunk_index'];
                });

                $chunk_texts = array();
                $chunks_in_this_source = 0;
                foreach ($group['chunks'] as $chunk) {
                    if ($total_chunks_used + $chunks_in_this_source >= $max_total_chunks) {
                        break;
                    }
                    $chunk_texts[] = $chunk['text'];
                    $chunks_in_this_source++;
                }
                $full_text = implode("\n\n", $chunk_texts);
            }
        } else {
            $full_text = $group['single_text'];
            $chunks_in_this_source = 1;
        }

        if (!empty($full_text)) {
            // Strip URLs from content if citation links are disabled
            if (!$citation_links_enabled) {
                $full_text = preg_replace('#\bhttps?://[^\s<>"\']+#i', '', $full_text);
                $full_text = preg_replace('/\s+/', ' ', trim($full_text)); // Clean up extra spaces
            }

            $content .= "## Reference " . ($matches_used + 1) . " ##\n";
            $content .= $full_text . "\n\n";

            // Only include citation URLs if citation links are enabled
            if ($citation_links_enabled && !empty($source_url) && $source_url !== '#') {
                $valid_urls[] = $source_url;
                $content .= "URL: " . $source_url . "\n\n";
            }

            // Extract any URLs from the text content itself (only if citation links enabled)
            if ($citation_links_enabled) {
                preg_match_all(
                    '#\bhttps?://[^\s<>"\']+#i',
                    $full_text,
                    $content_urls
                );
                if (!empty($content_urls[0])) {
                    $valid_urls = array_merge($valid_urls, $content_urls[0]);
                }
            }

            $matches_used++;
            $total_chunks_used += $chunks_in_this_source;
        }
    }

    // Process ALL matches for testing data (top 10) - with role checking for testing display
    $all_matches = [];
    foreach ($results['matches'] as $index => $match) {
        if ($index >= 10) break; // Limit to top 10 for testing
        
        $match_id = $match['id'] ?? '';
        
        // Check role access for testing display (use cache if available)
        $role_restriction = $this->get_single_vector_role($match_id, $match['metadata']);
        $has_access = $knowledge_manager->mxchat_user_has_content_access($role_restriction);
        
        $source_display = '';
        if (!empty($match['metadata']['source_url'])) {
            $source_display = $match['metadata']['source_url'];
        } else {
            $content_preview = strip_tags($match['metadata']['text'] ?? '');
            $content_preview = preg_replace('/\s+/', ' ', $content_preview);
            $source_display = substr(trim($content_preview), 0, 50) . '...';
        }
        
        $match_id_for_display = $match['id'] ?? $index;

        // Check for chunk metadata in Pinecone
        $is_chunk = isset($match['metadata']['is_chunked']) && $match['metadata']['is_chunked'];
        $chunk_index = isset($match['metadata']['chunk_index']) ? intval($match['metadata']['chunk_index']) : null;
        $total_chunks = isset($match['metadata']['total_chunks']) ? intval($match['metadata']['total_chunks']) : null;

        // Also detect chunk from vector ID pattern: {hash}_chunk_{index}
        if (!$is_chunk && MxChat_Chunker::is_chunk_vector_id($match_id_for_display)) {
            $is_chunk = true;
        }

        $all_matches[] = [
            'document_id' => $match_id_for_display,
            'similarity' => $match['score'],
            'similarity_percentage' => round($match['score'] * 100, 2),
            'above_threshold' => $match['score'] >= $similarity_threshold,
            'source_display' => $source_display,
            'content_preview' => substr(strip_tags($match['metadata']['text'] ?? ''), 0, 100) . '...',
            'used_for_context' => in_array($match_id_for_display, $matches_used_for_context),
            'role_restriction' => $role_restriction,
            'has_access' => $has_access,
            'filtered_out' => !$has_access,
            'is_chunk' => $is_chunk,
            'chunk_index' => $chunk_index,
            'total_chunks' => $total_chunks
        ];
    }
    
    // Store for testing panel
    $this->last_similarity_analysis['top_matches'] = $all_matches;
    $this->last_similarity_analysis['total_checked'] = count($results['matches']);
    
    // NEW: Store unique valid URLs for validation
    $this->current_valid_urls = array_unique($valid_urls);
    
    // Add response guidelines
    if ($matches_used === 0) {
        $content = "No reference information was found for this query.\n\n";
    } else {
        // Build response guidelines based on citation links setting
        $content .= "\n## Response Guidelines ##\n" .
                   "You are an AI Chatbot. Answer naturally and helpfully using only the information from the references above. " .
                   "Be conversational and friendly, but never mention your knowledge base or training data. " .
                   "If you don't have specific information or are uncertain about any details, it's always " .
                   "better to honestly say you don't know rather than making up or guessing at answers. " .
                   "When information is incomplete, let them know you are unsure.\n\n";

        // Only add hyperlink instructions if citation links are enabled
        if ($citation_links_enabled) {
            $content .= "CRITICAL: When creating hyperlinks, always use proper markdown format with descriptive text: " .
                       "[descriptive text](url). NEVER use empty brackets like [](url). The text in brackets must describe what the link is about.";
        } else {
            $content .= "IMPORTANT: Do not include any citation links, source URLs, or hyperlinks in your responses. " .
                       "Simply provide helpful answers based on the reference information without citing sources.";
        }
    }

    return trim($content);
}

/**
 * Get role restriction for a single vector (with caching)
 */
private function get_single_vector_role($vector_id, $metadata = array()) {
    global $wpdb;
    
    if (empty($vector_id)) {
        return 'public';
    }
    
    // Check cache first
    $cache_key = 'mxchat_vector_role_' . $vector_id;
    $cached_role = wp_cache_get($cache_key, 'mxchat_vector_roles');
    
    if ($cached_role !== false) {
        return $cached_role;
    }
    
    $role_restriction = 'public';
    
    // First try Pinecone metadata
    if (!empty($metadata['role_restriction'])) {
        $role_restriction = $metadata['role_restriction'];
    } else {
        // Check WordPress table for user-modified roles
        $roles_table = $wpdb->prefix . 'mxchat_pinecone_roles';
        $stored_role = $wpdb->get_var($wpdb->prepare(
            "SELECT role_restriction FROM {$roles_table} WHERE vector_id = %s",
            $vector_id
        ));
        
        if ($stored_role) {
            $role_restriction = $stored_role;
        }
    }
    
    // Cache individual role for 1 hour
    wp_cache_set($cache_key, $role_restriction, 'mxchat_vector_roles', 3600);

    return $role_restriction;
}

/**
 * Fetch and reassemble all chunks for a URL from Pinecone
 *
 * @param string $source_url The source URL to fetch chunks for
 * @param array $bot_config Bot-specific Pinecone configuration
 * @return string Reassembled content from all chunks
 */
private function reassemble_chunks_from_pinecone($source_url, $bot_config, $max_chunks = 0, &$chunk_count = 0) {
    $api_key = $bot_config['api_key'] ?? '';
    $host = $bot_config['host'] ?? '';
    $namespace = $bot_config['namespace'] ?? '';

    if (empty($host) || empty($api_key)) {
        $chunk_count = 0;
        return '';
    }

    $base_hash = md5($source_url);

    // Use Pinecone list API to find all chunk vectors with this prefix
    $list_url = "https://{$host}/vectors/list";

    // Limit to max_chunks if specified, otherwise fetch up to 100
    $fetch_limit = ($max_chunks > 0 && $max_chunks < 100) ? $max_chunks : 100;

    $list_body = array(
        'prefix' => $base_hash . '_chunk_',
        'limit' => $fetch_limit
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

    if (is_wp_error($list_response)) {
        //error_log('[MXCHAT-CHUNK] List API error: ' . $list_response->get_error_message());
        return '';
    }

    $list_data = json_decode(wp_remote_retrieve_body($list_response), true);

    if (empty($list_data['vectors'])) {
        //error_log('[MXCHAT-CHUNK] No chunk vectors found for URL: ' . $source_url);
        return '';
    }

    // Extract vector IDs
    $vector_ids = array();
    foreach ($list_data['vectors'] as $vector) {
        if (isset($vector['id'])) {
            $vector_ids[] = $vector['id'];
        }
    }

    if (empty($vector_ids)) {
        return '';
    }

    // Fetch all chunk content
    $fetch_url = "https://{$host}/vectors/fetch";

    $fetch_body = array(
        'ids' => $vector_ids
    );

    if (!empty($namespace)) {
        $fetch_body['namespace'] = $namespace;
    }

    $fetch_response = wp_remote_post($fetch_url, array(
        'headers' => array(
            'Api-Key' => $api_key,
            'accept' => 'application/json',
            'content-type' => 'application/json'
        ),
        'body' => wp_json_encode($fetch_body),
        'timeout' => 30
    ));

    if (is_wp_error($fetch_response)) {
        //error_log('[MXCHAT-CHUNK] Fetch API error: ' . $fetch_response->get_error_message());
        return '';
    }

    $fetch_data = json_decode(wp_remote_retrieve_body($fetch_response), true);

    if (empty($fetch_data['vectors'])) {
        return '';
    }

    // Sort chunks by index and reassemble
    $chunks = array();
    foreach ($fetch_data['vectors'] as $id => $vector) {
        $metadata = $vector['metadata'] ?? array();
        $chunk_index = $metadata['chunk_index'] ?? 0;
        $text = $metadata['text'] ?? '';

        // Store chunk with its index
        $chunks[$chunk_index] = $text;
    }

    // Sort by chunk index
    ksort($chunks);

    // Apply chunk limit if specified
    if ($max_chunks > 0 && count($chunks) > $max_chunks) {
        $chunks = array_slice($chunks, 0, $max_chunks, true);
    }

    // Store actual chunk count
    $chunk_count = count($chunks);

    // Reassemble content
    return implode("\n\n", $chunks);
}

/**
 * Search for relevant content using OpenAI Vector Store (File Search)
 *
 * @param string $user_query The user's query text
 * @param string $bot_id The bot ID
 * @param array $vectorstore_config Vector Store configuration
 * @return string Formatted context string with references
 */
private function find_relevant_content_openai_vectorstore($user_query, $bot_id = 'default', $vectorstore_config = array()) {
    error_log("MXCHAT DEBUG: find_relevant_content_openai_vectorstore called");
    error_log("  - bot_id: " . $bot_id);
    error_log("  - user_query length: " . strlen($user_query));

    // Get OpenAI API key
    $mxchat_options = get_option('mxchat_options', array());
    $api_key = $mxchat_options['api_key'] ?? '';

    if (empty($api_key)) {
        error_log("MXCHAT DEBUG ERROR: OpenAI API key not configured");
        $this->current_valid_urls = [];
        return '';
    }

    // Get Vector Store configuration
    if (empty($vectorstore_config)) {
        $vectorstore_config = $this->get_bot_vectorstore_config($bot_id);
    }

    $vectorstore_ids_string = $vectorstore_config['vectorstore_ids'] ?? '';
    $max_results = $vectorstore_config['max_results'] ?? 5;

    if (empty($vectorstore_ids_string)) {
        error_log("MXCHAT DEBUG ERROR: No Vector Store IDs configured");
        $this->current_valid_urls = [];
        return '';
    }

    // Parse Vector Store IDs
    $vectorstore_ids = array_map('trim', explode(',', $vectorstore_ids_string));
    $vectorstore_ids = array_filter($vectorstore_ids); // Remove empty values

    error_log("MXCHAT DEBUG: Vector Store IDs: " . implode(', ', $vectorstore_ids));
    error_log("MXCHAT DEBUG: Max results: " . $max_results);

    // Initialize similarity analysis storage
    $this->last_similarity_analysis = [
        'knowledge_base_type' => 'OpenAI Vector Store',
        'bot_id' => $bot_id,
        'vectorstore_ids' => $vectorstore_ids,
        'top_matches' => [],
        'threshold_used' => 0,
        'total_checked' => 0
    ];

    $valid_urls = [];

    // Get the selected model
    $bot_options = $this->get_bot_options($bot_id);
    $current_options = !empty($bot_options) ? $bot_options : $mxchat_options;
    $selected_model = $current_options['model'] ?? 'gpt-5.1-chat-latest';

    // Verify it's an OpenAI model
    if (!$this->is_openai_chat_model($selected_model)) {
        error_log("MXCHAT DEBUG ERROR: Vector Store search requires OpenAI model. Current: " . $selected_model);
        $this->current_valid_urls = [];
        return '';
    }

    // Use OpenAI Responses API with file_search tool
    $request_body = array(
        'model' => $selected_model,
        'input' => $user_query,
        'tools' => array(
            array(
                'type' => 'file_search',
                'vector_store_ids' => $vectorstore_ids,
                'max_num_results' => intval($max_results)
            )
        ),
        'include' => array('output[*].file_search_call.search_results')
    );

    error_log("MXCHAT VECTORSTORE: ========== REQUEST START ==========");
    error_log("MXCHAT VECTORSTORE: Model: " . $selected_model);
    error_log("MXCHAT VECTORSTORE: Query: " . substr($user_query, 0, 200));
    error_log("MXCHAT VECTORSTORE: Vector Store IDs: " . implode(', ', $vectorstore_ids));
    error_log("MXCHAT VECTORSTORE: Max Results: " . $max_results);
    error_log("MXCHAT VECTORSTORE: Request body: " . wp_json_encode($request_body));

    $response = wp_remote_post('https://api.openai.com/v1/responses', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => wp_json_encode($request_body),
        'timeout' => 60
    ));

    if (is_wp_error($response)) {
        error_log("MXCHAT VECTORSTORE ERROR: WP Error: " . $response->get_error_message());
        $this->current_valid_urls = [];
        return '';
    }

    $response_code = wp_remote_retrieve_response_code($response);
    error_log("MXCHAT VECTORSTORE: Response code: " . $response_code);

    $response_body = wp_remote_retrieve_body($response);
    error_log("MXCHAT VECTORSTORE: Raw response (first 2000 chars): " . substr($response_body, 0, 2000));

    if ($response_code !== 200) {
        error_log("MXCHAT VECTORSTORE ERROR: API error response: " . $response_body);
        $this->current_valid_urls = [];
        return '';
    }
    $result = json_decode($response_body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("MXCHAT VECTORSTORE ERROR: JSON decode error: " . json_last_error_msg());
        $this->current_valid_urls = [];
        return '';
    }

    // Debug: Log the structure of the result
    error_log("MXCHAT VECTORSTORE: Result keys: " . implode(', ', array_keys($result)));
    if (isset($result['output'])) {
        error_log("MXCHAT VECTORSTORE: Output count: " . count($result['output']));
        foreach ($result['output'] as $idx => $out) {
            error_log("MXCHAT VECTORSTORE: Output[$idx] type: " . ($out['type'] ?? 'unknown'));
            error_log("MXCHAT VECTORSTORE: Output[$idx] keys: " . implode(', ', array_keys($out)));
        }
    } else {
        error_log("MXCHAT VECTORSTORE: No 'output' key in result!");
    }

    // Extract file search results from the response
    $content = '';
    $matches_used = 0;
    $all_matches = [];

    // The Responses API returns output array with tool results
    if (isset($result['output']) && is_array($result['output'])) {
        foreach ($result['output'] as $output_item) {
            // Look for file_search_call results
            if (isset($output_item['type']) && $output_item['type'] === 'file_search_call') {
                error_log("MXCHAT VECTORSTORE: Found file_search_call output item");
                error_log("MXCHAT VECTORSTORE: file_search_call keys: " . implode(', ', array_keys($output_item)));

                // Check for search_results in the output item directly
                $search_results = $output_item['search_results'] ?? $output_item['results'] ?? [];
                error_log("MXCHAT VECTORSTORE: Search results count: " . count($search_results));

                if (empty($search_results)) {
                    error_log("MXCHAT VECTORSTORE: No search results found in file_search_call");
                    error_log("MXCHAT VECTORSTORE: file_search_call content: " . wp_json_encode($output_item));
                }

                foreach ($search_results as $index => $search_result) {
                    $filename = $search_result['filename'] ?? '';
                    $score = $search_result['score'] ?? 0;
                    $text_content = '';

                    // Extract text content from the result
                    // The text can be directly on the result OR nested under content array
                    if (isset($search_result['text']) && !empty($search_result['text'])) {
                        // Direct text field (OpenAI's actual format)
                        $text_content = $search_result['text'];
                        error_log("MXCHAT VECTORSTORE: Found text directly on result[$index], length: " . strlen($text_content));
                    } elseif (isset($search_result['content']) && is_array($search_result['content'])) {
                        // Nested content array format
                        foreach ($search_result['content'] as $content_item) {
                            if (isset($content_item['text'])) {
                                $text_content .= $content_item['text'] . "\n";
                            }
                        }
                        error_log("MXCHAT VECTORSTORE: Found text in content array for result[$index], length: " . strlen($text_content));
                    } else {
                        error_log("MXCHAT VECTORSTORE: No text found for result[$index]. Keys: " . implode(', ', array_keys($search_result)));
                    }

                    if (!empty($text_content)) {
                        $content .= "## Reference " . ($matches_used + 1) . " ##\n";
                        $content .= trim($text_content) . "\n\n";

                        if (!empty($filename)) {
                            $content .= "Source: " . $filename . "\n\n";
                        }

                        // Extract URLs from content
                        preg_match_all(
                            '#\bhttps?://[^\s<>"\']+#i',
                            $text_content,
                            $content_urls
                        );
                        if (!empty($content_urls[0])) {
                            $valid_urls = array_merge($valid_urls, $content_urls[0]);
                        }

                        $matches_used++;
                    }

                    // Store for similarity analysis
                    $all_matches[] = [
                        'document_id' => $filename ?: ('result_' . $index),
                        'similarity' => $score,
                        'similarity_percentage' => round($score * 100, 2),
                        'above_threshold' => true,
                        'source_display' => $filename,
                        'content_preview' => substr(strip_tags($text_content), 0, 100) . '...',
                        'used_for_context' => true,
                        'role_restriction' => 'public',
                        'has_access' => true,
                        'filtered_out' => false
                    ];
                }
            }

            // Also check for message content with annotations (citations)
            if (isset($output_item['type']) && $output_item['type'] === 'message') {
                if (isset($output_item['content']) && is_array($output_item['content'])) {
                    foreach ($output_item['content'] as $content_block) {
                        if (isset($content_block['annotations']) && is_array($content_block['annotations'])) {
                            foreach ($content_block['annotations'] as $annotation) {
                                if (isset($annotation['filename'])) {
                                    $filename = $annotation['filename'];
                                    $score = $annotation['score'] ?? 0;
                                    $text_content = '';

                                    if (isset($annotation['content']) && is_array($annotation['content'])) {
                                        foreach ($annotation['content'] as $ann_content) {
                                            if (isset($ann_content['text'])) {
                                                $text_content .= $ann_content['text'] . "\n";
                                            }
                                        }
                                    }

                                    if (!empty($text_content) && $matches_used < $max_results) {
                                        $content .= "## Reference " . ($matches_used + 1) . " ##\n";
                                        $content .= trim($text_content) . "\n\n";
                                        $content .= "Source: " . $filename . "\n\n";

                                        preg_match_all(
                                            '#\bhttps?://[^\s<>"\']+#i',
                                            $text_content,
                                            $content_urls
                                        );
                                        if (!empty($content_urls[0])) {
                                            $valid_urls = array_merge($valid_urls, $content_urls[0]);
                                        }

                                        $matches_used++;

                                        $all_matches[] = [
                                            'document_id' => $filename,
                                            'similarity' => $score,
                                            'similarity_percentage' => round($score * 100, 2),
                                            'above_threshold' => true,
                                            'source_display' => $filename,
                                            'content_preview' => substr(strip_tags($text_content), 0, 100) . '...',
                                            'used_for_context' => true,
                                            'role_restriction' => 'public',
                                            'has_access' => true,
                                            'filtered_out' => false
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // Store for testing panel
    $this->last_similarity_analysis['top_matches'] = $all_matches;
    $this->last_similarity_analysis['total_checked'] = count($all_matches);

    // Store unique valid URLs for validation
    $this->current_valid_urls = array_unique($valid_urls);

    error_log("MXCHAT VECTORSTORE: ========== SEARCH COMPLETE ==========");
    error_log("MXCHAT VECTORSTORE: Matches used: " . $matches_used);
    error_log("MXCHAT VECTORSTORE: All matches count: " . count($all_matches));
    error_log("MXCHAT VECTORSTORE: Content length: " . strlen($content));
    if ($matches_used > 0) {
        error_log("MXCHAT VECTORSTORE: Content preview: " . substr($content, 0, 500));
    }

    // Check if citation links are enabled
    $citation_links_enabled = ($mxchat_options['citation_links_toggle'] ?? 'on') === 'on';

    // Add response guidelines
    if ($matches_used === 0) {
        error_log("MXCHAT VECTORSTORE: No matches found - returning empty reference message");
        $content = "No reference information was found for this query.\n\n";
    } else {
        // Build response guidelines based on citation links setting
        $content .= "\n## Response Guidelines ##\n" .
                   "You are an AI Chatbot. Answer naturally and helpfully using only the information from the references above. " .
                   "Be conversational and friendly, but never mention your knowledge base or training data. " .
                   "If you don't have specific information or are uncertain about any details, it's always " .
                   "better to honestly say you don't know rather than making up or guessing at answers. " .
                   "When information is incomplete, let them know you are unsure.\n\n";

        // Only add hyperlink instructions if citation links are enabled
        if ($citation_links_enabled) {
            $content .= "CRITICAL: When creating hyperlinks, always use proper markdown format with descriptive text: " .
                       "[descriptive text](url). NEVER use empty brackets like [](url). The text in brackets must describe what the link is about.";
        } else {
            $content .= "IMPORTANT: Do not include any citation links, source URLs, or hyperlinks in your responses. " .
                       "Simply provide helpful answers based on the reference information without citing sources.";
        }
    }

    error_log("MXCHAT DEBUG: Vector Store search complete. Matches used: " . $matches_used);

    return trim($content);
}

/**
 * Check if the given model is an OpenAI chat model
 *
 * @param string $model The model ID
 * @return bool True if it's an OpenAI model
 */
private function is_openai_chat_model($model) {
    $openai_prefixes = array('gpt-', 'o1-', 'o3-');
    foreach ($openai_prefixes as $prefix) {
        if (strpos($model, $prefix) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Get bot-specific Vector Store configuration
 *
 * @param string $bot_id The bot ID
 * @return array Configuration array
 */
private function get_bot_vectorstore_config($bot_id = 'default') {
    $vectorstore_options = get_option('mxchat_openai_vectorstore_options', array());

    // Default global settings
    $default_config = array(
        'use_vectorstore' => ($vectorstore_options['mxchat_use_openai_vectorstore'] ?? '0') === '1',
        'vectorstore_ids' => $vectorstore_options['mxchat_vectorstore_ids'] ?? '',
        'max_results' => $vectorstore_options['mxchat_vectorstore_max_results'] ?? 5
    );

    // Allow multi-bot plugin to override with bot-specific settings
    $bot_config = apply_filters('mxchat_get_bot_vectorstore_config', $default_config, $bot_id);

    // Preserve max_results from global settings if not set in bot config
    if (!isset($bot_config['max_results'])) {
        $bot_config['max_results'] = $default_config['max_results'];
    }

    return $bot_config;
}

private function mxchat_find_relevant_products($user_embedding) {
    //error_log('MXChat Vector Search: Starting product search...');

    // Retrieve the add-on settings from the database
    $addon_options = get_option('mxchat_pinecone_addon_options', array());

    // Determine whether Pinecone is enabled
    $use_pinecone = (isset($addon_options['mxchat_use_pinecone']) && $addon_options['mxchat_use_pinecone'] === '1') ? 1 : 0;

    //error_log('Pinecone enabled flag: ' . $use_pinecone);

    if ($use_pinecone === 1) {
        //error_log('MXChat Vector Search: Using Pinecone database for products');
        return $this->find_relevant_products_pinecone($user_embedding);
    } else {
        //error_log('MXChat Vector Search: Using WordPress database for products');
        return $this->find_relevant_products_wordpress($user_embedding);
    }
}
private function find_relevant_products_wordpress($user_embedding) {
    global $wpdb;
    $system_prompt_table = $wpdb->prefix . 'mxchat_system_prompt_content';
    $cache_key = 'mxchat_system_prompt_embeddings';
    $batch_size = 500;

    // Original WordPress database search logic
    // [Previous implementation remains the same]
    $embeddings = wp_cache_get($cache_key, 'mxchat_system_prompts');
    if ($embeddings === false) {
        $embeddings = [];
        $offset = 0;

        do {
            $query = $wpdb->prepare(
                "SELECT id, embedding_vector
                FROM {$system_prompt_table}
                LIMIT %d OFFSET %d",
                $batch_size,
                $offset
            );

            $batch = $wpdb->get_results($query);
            if (empty($batch)) {
                break;
            }

            $embeddings = array_merge($embeddings, $batch);
            $offset += $batch_size;

            unset($batch);

        } while (true);

        if (empty($embeddings)) {
            return '';
        }
        wp_cache_set($cache_key, $embeddings, 'mxchat_system_prompts', 3600);
    }

    $relevant_results = [];
    foreach ($embeddings as $embedding) {
        $database_embedding = $embedding->embedding_vector
            ? unserialize($embedding->embedding_vector, ['allowed_classes' => false])
            : null;
        if (is_array($database_embedding) && is_array($user_embedding)) {
            $similarity = $this->mxchat_calculate_cosine_similarity($user_embedding, $database_embedding);
            $relevant_results[] = [
                'id' => $embedding->id,
                'similarity' => $similarity
            ];
        }
        unset($database_embedding);
    }

    // Use fixed threshold for products
    $similarity_threshold = 0.85;

    $relevant_results = array_filter($relevant_results, function ($result) use ($similarity_threshold) {
        return $result['similarity'] >= $similarity_threshold;
    });
    usort($relevant_results, function ($a, $b) {
        return $b['similarity'] <=> $a['similarity'];
    });

    $top_results = array_slice($relevant_results, 0, 3);
    $content = '';

    foreach ($top_results as $result) {
        $chunk_content = $this->fetch_content_with_product_links($result['id']);
        $content .= $chunk_content . "\n\n";
    }

    return trim($content);
}


private function find_relevant_products_pinecone($user_embedding) {
    //error_log('Starting Pinecone product search...');

    $options = get_option('mxchat_pinecone_addon_options', array());
    $api_key = $options['mxchat_pinecone_api_key'] ?? '';
    $host = $options['mxchat_pinecone_host'] ?? '';

    if (empty($host) || empty($api_key)) {
        //error_log('Pinecone credentials not properly configured for product search');
        return '';
    }

    $similarity_threshold = 0.85;
    $api_endpoint = "https://{$host}/query";

    $request_body = array(
        'vector' => $user_embedding,
        'topK' => 5,
        'includeMetadata' => true,
        'includeValues' => true,
        'filter' => array(
            'type' => 'product'
        )
    );

    //error_log('Sending request to Pinecone with body: ' . wp_json_encode($request_body));

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
        //error_log('Pinecone product query error: ' . $response->get_error_message());
        return '';
    }

    $response_code = wp_remote_retrieve_response_code($response);
    //error_log('Pinecone response code: ' . $response_code);

    if ($response_code !== 200) {
        //error_log('Pinecone API error during product search: ' . wp_remote_retrieve_body($response));
        return '';
    }

    $results = json_decode(wp_remote_retrieve_body($response), true);
    //error_log('Pinecone raw response: ' . wp_remote_retrieve_body($response));

    if (empty($results['matches'])) {
        //error_log('No matches found in Pinecone response');
        return '';
    }

    $content = '';
    foreach ($results['matches'] as $match) {
        if ($match['score'] < $similarity_threshold) {
            //error_log("Match below threshold: " . $match['score']);
            continue;
        }

        if (!empty($match['metadata']['text'])) {
            $content .= $match['metadata']['text'];
            if (!empty($match['metadata']['source_url'])) {
                $content .= "\n\nFor more details, check out this product: " . esc_url($match['metadata']['source_url']);
            }
            $content .= "\n\n";
        }
    }

    return trim($content);
}


private function fetch_content_with_product_links($most_relevant_id) {
    global $wpdb;
    $system_prompt_table = $wpdb->prefix . 'mxchat_system_prompt_content';

    // Fetch the article content and associated product URL
    $query = $wpdb->prepare("SELECT article_content, source_url FROM {$system_prompt_table} WHERE id = %d", $most_relevant_id);
    $result = $wpdb->get_row($query);

    if ($result) {
        // Append the product link to the content if available
        $content = $result->article_content;
        if (!empty($result->source_url)) {
            $content .= "\n\nFor more details, check out this product: " . esc_url($result->source_url);
        }
        return $content;
    }

    return null;
}

/**
 * Get system instructions for a specific bot or default
 * Checks for multi-bot add-on and uses bot-specific instructions if available
 * Automatically strips URLs if citation links are disabled
 * Replaces {visitor_name} placeholder with actual visitor name if available
 *
 * @param string $bot_id The bot ID to get instructions for
 * @param string $session_id Optional session ID to lookup visitor name
 */
private function get_system_instructions($bot_id = 'default', $session_id = '') {
    $instructions = '';

    // Check if multi-bot add-on is active
    if (class_exists('MxChat_Multi_Bot_Core_Manager') && $bot_id !== 'default') {
        // Get bot-specific options from multi-bot add-on
        $bot_options = apply_filters('mxchat_get_bot_options', array(), $bot_id);

        // If bot has custom system instructions, use those
        if (!empty($bot_options['system_prompt_instructions'])) {
            $instructions = $bot_options['system_prompt_instructions'];
        }
    }

    // Fall back to default system instructions
    if (empty($instructions)) {
        $instructions = isset($this->options['system_prompt_instructions']) ? $this->options['system_prompt_instructions'] : '';
    }

    // Check if citation links are disabled - if so, strip URLs from instructions
    $fresh_options = get_option('mxchat_options', []);
    $citation_links_enabled = isset($fresh_options['citation_links_toggle']) ? ($fresh_options['citation_links_toggle'] === 'on') : true;

    if (!$citation_links_enabled && !empty($instructions)) {
        $instructions = preg_replace('#\bhttps?://[^\s<>"\']+#i', '', $instructions);
        $instructions = preg_replace('/\s+/', ' ', trim($instructions)); // Clean up extra spaces
    }

    // Replace {visitor_name} placeholder with actual visitor name if available
    if (!empty($instructions) && !empty($session_id) && stripos($instructions, '{visitor_name}') !== false) {
        $name_option_key = "mxchat_name_{$session_id}";
        $visitor_name = get_option($name_option_key, '');

        if (!empty($visitor_name)) {
            $instructions = str_ireplace('{visitor_name}', sanitize_text_field($visitor_name), $instructions);
        } else {
            // Remove placeholder if no name is available
            $instructions = str_ireplace('{visitor_name}', '', $instructions);
            $instructions = preg_replace('/\s{2,}/', ' ', trim($instructions)); // Clean up extra spaces
        }
    }

    return $instructions;
}
/**
 * Get the current bot ID from session or request context
 */
private function get_current_bot_id($session_id = '') {
    // First, check if bot_id is passed in the current request
    if (isset($_POST['bot_id']) && !empty($_POST['bot_id'])) {
        return sanitize_key($_POST['bot_id']);
    }
    
    // If not in POST, try to get it from session data
    if (!empty($session_id)) {
        $bot_id = get_option("mxchat_session_bot_{$session_id}", '');
        if (!empty($bot_id)) {
            return $bot_id;
        }
    }
    
    // Fall back to default
    return 'default';
}
private function mxchat_generate_response($relevant_content, $api_key, $xai_api_key, $claude_api_key, $deepseek_api_key, $gemini_api_key, $openrouter_api_key, $conversation_history, $streaming = false, $session_id = '', $testing_data = null, $selected_model = 'gpt-5.1-chat-latest') {
    try {
        if (!$relevant_content) {
            $error_response = [
                'error' => esc_html__("I couldn't find relevant information on that topic.", 'mxchat'),
                'error_code' => 'no_relevant_content'
            ];
            
            if ($testing_data !== null) {
                $error_response['testing_data'] = $testing_data;
            }
            
            return $error_response;
        }
        
        if (!is_array($conversation_history)) {
            $conversation_history = array();
        }
        
        // Check if this is an OpenRouter model
        if ($selected_model === 'openrouter') {
            // Get the actual OpenRouter model from options
            $openrouter_selected_model = $this->options['openrouter_selected_model'] ?? '';
            
            if (empty($openrouter_selected_model)) {
                $error_response = [
                    'error' => esc_html__('No OpenRouter model selected. Please select a model in settings.', 'mxchat'),
                    'error_code' => 'no_openrouter_model_selected'
                ];
                if ($testing_data !== null) {
                    $error_response['testing_data'] = $testing_data;
                }
                return $error_response;
            }
            
            if (empty($openrouter_api_key)) {
                $error_response = [
                    'error' => esc_html__('OpenRouter API key is not configured', 'mxchat'),
                    'error_code' => 'missing_openrouter_api_key'
                ];
                if ($testing_data !== null) {
                    $error_response['testing_data'] = $testing_data;
                }
                return $error_response;
            }
            
            if ($streaming) {
                return $this->mxchat_generate_response_openrouter_stream(
                    $openrouter_selected_model,
                    $openrouter_api_key,
                    $conversation_history,
                    $relevant_content,
                    $session_id,
                    $testing_data
                );
            } else {
                $response = $this->mxchat_generate_response_openrouter(
                    $openrouter_selected_model,
                    $openrouter_api_key,
                    $conversation_history,
                    $relevant_content
                );
            }
            
            if (is_array($response) && isset($response['error'])) {
                if ($testing_data !== null) {
                    $response['testing_data'] = $testing_data;
                }
                return $response;
            }
            
            return $response;
        }

        // Extract model prefix to determine the provider
        $model_parts = explode('-', $selected_model);
        $provider = strtolower($model_parts[0]);
        
        // Handle model selection based on provider prefix
        switch ($provider) {
            case 'gemini':
                if (empty($gemini_api_key)) {
                    $error_response = [
                        'error' => esc_html__('Google Gemini API key is not configured', 'mxchat'),
                        'error_code' => 'missing_gemini_api_key'
                    ];
                    if ($testing_data !== null) {
                        $error_response['testing_data'] = $testing_data;
                    }
                    return $error_response;
                }
                $response = $this->mxchat_generate_response_gemini(
                    $selected_model,
                    $gemini_api_key,
                    $conversation_history,
                    $relevant_content
                );
                break;
                
            case 'claude':
                if (empty($claude_api_key)) {
                    $error_response = [
                        'error' => esc_html__('Claude API key is not configured', 'mxchat'),
                        'error_code' => 'missing_claude_api_key'
                    ];
                    if ($testing_data !== null) {
                        $error_response['testing_data'] = $testing_data;
                    }
                    return $error_response;
                }
                if ($streaming) {
                    return $this->mxchat_generate_response_claude_stream(
                        $selected_model,
                        $claude_api_key,
                        $conversation_history,
                        $relevant_content,
                        $session_id,
                        $testing_data
                    );
                } else {
                    $response = $this->mxchat_generate_response_claude(
                        $selected_model,
                        $claude_api_key,
                        $conversation_history,
                        $relevant_content
                    );
                }
                break;
                
            case 'grok':
                if (empty($xai_api_key)) {
                    $error_response = [
                        'error' => esc_html__('X.AI API key is not configured', 'mxchat'),
                        'error_code' => 'missing_xai_api_key'
                    ];
                    if ($testing_data !== null) {
                        $error_response['testing_data'] = $testing_data;
                    }
                    return $error_response;
                }
                if ($streaming) {
                    return $this->mxchat_generate_response_xai_stream(
                        $selected_model,
                        $xai_api_key,
                        $conversation_history,
                        $relevant_content,
                        $session_id,
                        $testing_data
                    );
                } else {
                    $response = $this->mxchat_generate_response_xai(
                        $selected_model,
                        $xai_api_key,
                        $conversation_history,
                        $relevant_content
                    );
                }
                break;
                
            case 'deepseek':
                if (empty($deepseek_api_key)) {
                    $error_response = [
                        'error' => esc_html__('DeepSeek API key is not configured', 'mxchat'),
                        'error_code' => 'missing_deepseek_api_key'
                    ];
                    if ($testing_data !== null) {
                        $error_response['testing_data'] = $testing_data;
                    }
                    return $error_response;
                }
                if ($streaming) {
                    return $this->mxchat_generate_response_deepseek_stream(
                        $selected_model,
                        $deepseek_api_key,
                        $conversation_history,
                        $relevant_content,
                        $session_id,
                        $testing_data
                    );
                } else {
                    $response = $this->mxchat_generate_response_deepseek(
                        $selected_model,
                        $deepseek_api_key,
                        $conversation_history,
                        $relevant_content
                    );
                }
                break;
                
            case 'gpt':
            case 'o1':
                if (empty($api_key)) {
                    $error_response = [
                        'error' => esc_html__('OpenAI API key is not configured', 'mxchat'),
                        'error_code' => 'missing_openai_api_key'
                    ];
                    if ($testing_data !== null) {
                        $error_response['testing_data'] = $testing_data;
                    }
                    return $error_response;
                }

                // Check if web search is enabled for this OpenAI model
                $web_search_enabled = isset($this->options['enable_web_search']) && $this->options['enable_web_search'] === 'on';
                // Models that don't support web search
                $unsupported_web_search_models = array('gpt-4.1-nano');
                $model_supports_web_search = !in_array($selected_model, $unsupported_web_search_models);

                if ($web_search_enabled && $model_supports_web_search) {
                    // Use Responses API with web search
                    return $this->mxchat_generate_response_openai_web_search(
                        $selected_model,
                        $api_key,
                        $conversation_history,
                        $relevant_content,
                        $session_id,
                        $testing_data,
                        $streaming
                    );
                } elseif ($streaming) {
                    return $this->mxchat_generate_response_openai_stream(
                        $selected_model,
                        $api_key,
                        $conversation_history,
                        $relevant_content,
                        $session_id,
                        $testing_data
                    );
                } else {
                    $response = $this->mxchat_generate_response_openai(
                        $selected_model,
                        $api_key,
                        $conversation_history,
                        $relevant_content
                    );
                }
                break;
                
            default:
                if (empty($api_key)) {
                    $error_response = [
                        'error' => esc_html__('OpenAI API key is not configured', 'mxchat'),
                        'error_code' => 'missing_openai_api_key'
                    ];
                    if ($testing_data !== null) {
                        $error_response['testing_data'] = $testing_data;
                    }
                    return $error_response;
                }

                // Check if web search is enabled (default case also handles OpenAI models)
                $web_search_enabled = isset($this->options['enable_web_search']) && $this->options['enable_web_search'] === 'on';
                $unsupported_web_search_models = array('gpt-4.1-nano');
                $model_supports_web_search = !in_array($selected_model, $unsupported_web_search_models);

                if ($web_search_enabled && $model_supports_web_search) {
                    return $this->mxchat_generate_response_openai_web_search(
                        $selected_model,
                        $api_key,
                        $conversation_history,
                        $relevant_content,
                        $session_id,
                        $testing_data,
                        $streaming
                    );
                } elseif ($streaming) {
                    return $this->mxchat_generate_response_openai_stream(
                        $selected_model,
                        $api_key,
                        $conversation_history,
                        $relevant_content,
                        $session_id,
                        $testing_data
                    );
                } else {
                    $response = $this->mxchat_generate_response_openai(
                        $selected_model,
                        $api_key,
                        $conversation_history,
                        $relevant_content
                    );
                }
                break;
        }
        
        if (is_array($response) && isset($response['error'])) {
            if ($testing_data !== null) {
                $response['testing_data'] = $testing_data;
            }
            return $response;
        }
        
        return $response;
        
    } catch (Exception $e) {
        $error_response = [
            'error' => sprintf(esc_html__('An error occurred: %s', 'mxchat'), esc_html($e->getMessage())),
            'error_code' => 'system_exception',
            'exception_details' => $e->getMessage()
        ];
        
        if ($testing_data !== null) {
            $error_response['testing_data'] = $testing_data;
        }
        
        return $error_response;
    }
}
private function mxchat_generate_response_openrouter_stream($selected_model, $openrouter_api_key, $conversation_history, $relevant_content, $session_id, $testing_data = null) {
    try {
        $bot_id = $this->get_current_bot_id($session_id);
        $system_prompt_instructions = $this->get_system_instructions($bot_id, $session_id);
        
        if (!is_array($conversation_history)) {
            $conversation_history = array();
        }

        $formatted_conversation = array();

        $formatted_conversation[] = array(
            'role' => 'system',
            'content' => $system_prompt_instructions . " " . $relevant_content
        );

        foreach ($conversation_history as $message) {
            if (is_array($message) && isset($message['role']) && isset($message['content'])) {
                $role = $message['role'];
                if ($role === 'bot' || $role === 'agent') {
                    $role = 'assistant';
                }
                if (!in_array($role, ['system', 'assistant', 'user'])) {
                    $role = 'user';
                }
                $formatted_conversation[] = array(
                    'role' => $role,
                    'content' => $message['content']
                );
            }
        }

        if (headers_sent() || !function_exists('curl_init')) {
            $regular_response = $this->mxchat_generate_response_openrouter(
                $selected_model,
                $openrouter_api_key,
                $conversation_history,
                $relevant_content
            );
            
            // Save bot response to transcript
            if (!empty($regular_response) && !empty($session_id)) {
                $this->mxchat_save_chat_message($session_id, 'bot', $regular_response);
            }
            
            $response_data = [
                'text' => $regular_response,
                'html' => '',
                'session_id' => $session_id
            ];
            
            if ($testing_data !== null) {
                $response_data['testing_data'] = $testing_data;
            }
            
            header('Content-Type: application/json');
            echo json_encode($response_data);
            return true;
        }

        $body = json_encode([
            'model' => $selected_model,
            'messages' => $formatted_conversation,
            'temperature' => 1,
            'stream' => true
        ]);

        // Setup streaming headers now that we know we're actually streaming
        $this->setup_streaming_headers();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://openrouter.ai/api/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $openrouter_api_key,
            'HTTP-Referer: ' . home_url(),
            'X-Title: ' . get_bloginfo('name')
        ));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $full_response = '';
        $stream_started = false;
        $buffer = '';
        
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$full_response, &$stream_started, &$buffer, $testing_data) {
            if (!$stream_started && $testing_data !== null) {
                echo "data: " . json_encode(['testing_data' => $testing_data]) . "\n\n";
                flush();
                $stream_started = true;
            }
            
            $buffer .= $data;
            $lines = explode("\n", $buffer);
            $buffer = array_pop($lines);
            
            foreach ($lines as $line) {
                if (trim($line) === '') {
                    continue;
                }
                
                if (strpos($line, 'data: ') !== 0) {
                    continue;
                }
                
                $json_str = substr($line, 6);
                
                if (trim($json_str) === '[DONE]') {
                    echo "data: [DONE]\n\n";
                    flush();
                    continue;
                }
                
                $json = json_decode(trim($json_str), true);
                if ($json && isset($json['choices'][0]['delta']['content'])) {
                    $content = $json['choices'][0]['delta']['content'];
                    $full_response .= $content;
                    
                    echo "data: " . json_encode(['content' => $content]) . "\n\n";
                    flush();
                }
            }
            
            return strlen($data);
        });
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch) || $http_code !== 200) {
            curl_close($ch);
            
            $regular_response = $this->mxchat_generate_response_openrouter(
                $selected_model,
                $openrouter_api_key,
                $conversation_history,
                $relevant_content
            );
            
            $response_data = [
                'text' => $regular_response,
                'html' => '',
                'session_id' => $session_id
            ];
            
            if ($testing_data !== null) {
                $response_data['testing_data'] = $testing_data;
            }
            
            header('Content-Type: application/json');
            echo json_encode($response_data);
            return true;
        }
        
        curl_close($ch);
        
        if (!empty($full_response) && !empty($session_id)) {
            // Prepare RAG context for streaming response
            $rag_context_for_storage = null;
            $has_rag_data = $this->last_similarity_analysis !== null && !empty($this->last_similarity_analysis['top_matches']);
            $has_action_data = isset($this->last_action_analysis) && !empty($this->last_action_analysis);

            if ($has_rag_data || $has_action_data) {
                $rag_context_for_storage = [];

                if ($has_rag_data) {
                    $rag_context_for_storage['top_matches'] = $this->last_similarity_analysis['top_matches'];
                    $rag_context_for_storage['approved_urls'] = $this->current_valid_urls ?? [];
                    $rag_context_for_storage['similarity_threshold'] = $this->last_similarity_analysis['threshold_used'] ?? 0.35;
                    $rag_context_for_storage['knowledge_base_type'] = $this->last_similarity_analysis['knowledge_base_type'] ?? 'WordPress Database';
                    $rag_context_for_storage['total_documents_checked'] = $this->last_similarity_analysis['total_checked'] ?? 0;
                }

                if ($has_action_data) {
                    $rag_context_for_storage['action_analysis'] = $this->last_action_analysis;
                }
            }
            $this->mxchat_save_chat_message($session_id, 'bot', $full_response, null, $rag_context_for_storage);
        }

        return true;

    } catch (Exception $e) {
        $regular_response = $this->mxchat_generate_response_openrouter(
            $selected_model,
            $openrouter_api_key,
            $conversation_history,
            $relevant_content
        );
        
        $response_data = [
            'text' => $regular_response,
            'html' => '',
            'session_id' => $session_id
        ];
        
        if ($testing_data !== null) {
            $response_data['testing_data'] = $testing_data;
        }
        
        header('Content-Type: application/json');
        echo json_encode($response_data);
        return true;
    }
}
private function mxchat_generate_response_openai_stream($selected_model, $api_key, $conversation_history, $relevant_content, $session_id, $testing_data = null) {
    try {
        $bot_id = $this->get_current_bot_id($session_id);
        
        // Get system prompt instructions using centralized function
        $system_prompt_instructions = $this->get_system_instructions($bot_id, $session_id);
        
        // Ensure conversation_history is an array
        if (!is_array($conversation_history)) {
            $conversation_history = array();
        }

        // Format conversation history for OpenAI
        $formatted_conversation = array();

        $formatted_conversation[] = array(
            'role' => 'system',
            'content' => $system_prompt_instructions . " " . $relevant_content
        );

        foreach ($conversation_history as $message) {
            if (is_array($message) && isset($message['role']) && isset($message['content'])) {
                $role = $message['role'];
                if ($role === 'bot' || $role === 'agent') {
                    $role = 'assistant';
                }
                if (!in_array($role, ['system', 'assistant', 'user', 'function', 'tool'])) {
                    $role = 'user';
                }
                $formatted_conversation[] = array(
                    'role' => $role,
                    'content' => $message['content']
                );
            }
        }

        // Check if we can actually stream
        if (headers_sent() || !function_exists('curl_init')) {
            // Fallback to regular response with testing data
            $regular_response = $this->mxchat_generate_response_openai(
                $selected_model,
                $api_key,
                $conversation_history,
                $relevant_content
            );
            
            // Save bot response to transcript
            if (!empty($regular_response) && !empty($session_id)) {
                $this->mxchat_save_chat_message($session_id, 'bot', $regular_response);
            }
            
            $response_data = [
                'text' => $regular_response,
                'html' => '',
                'session_id' => $session_id
            ];
            
            if ($testing_data !== null) {
                $response_data['testing_data'] = $testing_data;
            }
            
            header('Content-Type: application/json');
            echo json_encode($response_data);
            return true;
        }

        // Check if this is a GPT-5 model (supports reasoning_effort parameter)
        $is_gpt5_model = (
            strpos($selected_model, 'gpt-5') === 0 ||
            $selected_model === 'gpt-5.2' ||
            $selected_model === 'gpt-5.1-2025-11-13' ||
            $selected_model === 'gpt-5' ||
            $selected_model === 'gpt-5-mini' ||
            $selected_model === 'gpt-5-nano'
        );

        // Build request body with optimal settings for fast streaming
        $request_body = [
            'model' => $selected_model,
            'messages' => $formatted_conversation,
            'temperature' => 1,
            'stream' => true
        ];

        // Add reasoning_effort only for GPT-5 models that support it
        // gpt-5.2 and gpt-5.1-chat-latest don't support reasoning_effort parameter
        if ($is_gpt5_model && $selected_model !== 'gpt-5.2' && $selected_model !== 'gpt-5.1-chat-latest') {
            // GPT-5.1 uses 'low' instead of 'minimal'
            if ($selected_model === 'gpt-5.1-2025-11-13') {
                $request_body['reasoning_effort'] = 'low';
            } else {
                $request_body['reasoning_effort'] = 'minimal'; // For other GPT-5 models
            }
        }

        $body = json_encode($request_body);

        // Setup streaming headers now that we know we're actually streaming
        $this->setup_streaming_headers();

        // Use cURL for streaming support
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $full_response = ''; // Accumulate full response for saving
        $stream_started = false;
        $buffer = ''; // CRITICAL: Add persistent buffer for incomplete chunks
        
        // Buffer control for real-time streaming
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$full_response, &$stream_started, &$buffer, $testing_data) {
            // Send testing data as the first event if available
            if (!$stream_started && $testing_data !== null) {
                echo "data: " . json_encode(['testing_data' => $testing_data]) . "\n\n";
                flush();
                $stream_started = true;
            }
            
            // CRITICAL FIX: Append new data to buffer
            $buffer .= $data;
            
            // Process complete lines only
            $lines = explode("\n", $buffer);
            
            // CRITICAL FIX: Keep the last incomplete line in the buffer
            // The last element might be incomplete, so keep it in buffer
            $buffer = array_pop($lines);
            
            foreach ($lines as $line) {
                // Skip empty lines
                if (trim($line) === '') {
                    continue;
                }
                
                // Only process lines that start with "data: "
                if (strpos($line, 'data: ') !== 0) {
                    continue;
                }
                
                $json_str = substr($line, 6); // Remove 'data: ' prefix
                
                if (trim($json_str) === '[DONE]') {
                    echo "data: [DONE]\n\n";
                    flush();
                    continue;
                }
                
                // Try to decode JSON
                $json = json_decode(trim($json_str), true);
                if ($json && isset($json['choices'][0]['delta']['content'])) {
                    $content = $json['choices'][0]['delta']['content'];
                    $full_response .= $content; // Accumulate the full response
                    
                    // Send as SSE format
                    echo "data: " . json_encode(['content' => $content]) . "\n\n";
                    flush();
                }
            }
            
            return strlen($data);
        });
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch) || $http_code !== 200) {
            $curl_error = curl_error($ch);
            curl_close($ch);

            // Fallback to regular response
            $regular_response = $this->mxchat_generate_response_openai(
                $selected_model,
                $api_key,
                $conversation_history,
                $relevant_content
            );

            // FIXED: Check if regular response returned an error
            if (is_array($regular_response) && isset($regular_response['error'])) {
                // Send error in SSE format since we're in streaming mode
                echo "data: " . json_encode([
                    'error' => true,
                    'error_message' => $regular_response['error'],
                    'error_code' => $regular_response['error_code'] ?? 'api_error',
                    'text' => $regular_response['error'],
                    'message' => $regular_response['error']
                ]) . "\n\n";
                echo "data: [DONE]\n\n";
                flush();
                return true;
            }

            $response_data = [
                'text' => $regular_response,
                'html' => '',
                'session_id' => $session_id
            ];

            if ($testing_data !== null) {
                $response_data['testing_data'] = $testing_data;
            }

            header('Content-Type: application/json');
            echo json_encode($response_data);
            return true;
        }
        
        curl_close($ch);

        // Save the complete response to maintain chat persistence
        if (!empty($full_response) && !empty($session_id)) {
            // Prepare RAG context for streaming response
            $rag_context_for_storage = null;
            $has_rag_data = $this->last_similarity_analysis !== null && !empty($this->last_similarity_analysis['top_matches']);
            $has_action_data = isset($this->last_action_analysis) && !empty($this->last_action_analysis);

            if ($has_rag_data || $has_action_data) {
                $rag_context_for_storage = [];

                if ($has_rag_data) {
                    $rag_context_for_storage['top_matches'] = $this->last_similarity_analysis['top_matches'];
                    $rag_context_for_storage['approved_urls'] = $this->current_valid_urls ?? [];
                    $rag_context_for_storage['similarity_threshold'] = $this->last_similarity_analysis['threshold_used'] ?? 0.35;
                    $rag_context_for_storage['knowledge_base_type'] = $this->last_similarity_analysis['knowledge_base_type'] ?? 'WordPress Database';
                    $rag_context_for_storage['total_documents_checked'] = $this->last_similarity_analysis['total_checked'] ?? 0;
                }

                if ($has_action_data) {
                    $rag_context_for_storage['action_analysis'] = $this->last_action_analysis;
                }
            }
            $this->mxchat_save_chat_message($session_id, 'bot', $full_response, null, $rag_context_for_storage);
        }

        return true; // Indicate streaming completed successfully

    } catch (Exception $e) {
        // Fallback to regular response
        $regular_response = $this->mxchat_generate_response_openai(
            $selected_model,
            $api_key,
            $conversation_history,
            $relevant_content
        );

        // FIXED: Check if regular response returned an error
        if (is_array($regular_response) && isset($regular_response['error'])) {
            // Send error in SSE format since we're in streaming mode
            echo "data: " . json_encode([
                'error' => true,
                'error_message' => $regular_response['error'],
                'error_code' => $regular_response['error_code'] ?? 'api_error',
                'text' => $regular_response['error'],
                'message' => $regular_response['error']
            ]) . "\n\n";
            echo "data: [DONE]\n\n";
            flush();
            return true;
        }

        $response_data = [
            'text' => $regular_response,
            'html' => '',
            'session_id' => $session_id
        ];

        if ($testing_data !== null) {
            $response_data['testing_data'] = $testing_data;
        }

        header('Content-Type: application/json');
        echo json_encode($response_data);
        return true;
    }
}

/**
 * Generate response using OpenAI Responses API with web search tool
 * This uses the newer Responses API which supports web search functionality
 */
private function mxchat_generate_response_openai_web_search($selected_model, $api_key, $conversation_history, $relevant_content, $session_id, $testing_data = null, $streaming = false) {
    try {
        $bot_id = $this->get_current_bot_id($session_id);
        $system_prompt_instructions = $this->get_system_instructions($bot_id, $session_id);

        if (!is_array($conversation_history)) {
            $conversation_history = array();
        }

        // Build the input for Responses API
        // The Responses API uses a different format - we need to construct the input properly
        $input_parts = [];

        // Add system instructions as context
        $system_context = $system_prompt_instructions . "\n\n" . $relevant_content;

        // Build conversation as input items for Responses API
        foreach ($conversation_history as $message) {
            if (is_array($message) && isset($message['role']) && isset($message['content'])) {
                $role = $message['role'];
                if ($role === 'bot' || $role === 'agent') {
                    $role = 'assistant';
                }
                if (!in_array($role, ['assistant', 'user'])) {
                    $role = 'user';
                }
                $input_parts[] = [
                    'type' => 'message',
                    'role' => $role,
                    'content' => $message['content']
                ];
            }
        }

        // Build request body for Responses API with web search
        $request_body = [
            'model' => $selected_model,
            'input' => $input_parts,
            'instructions' => $system_context,
            'tools' => [
                ['type' => 'web_search']
            ],
            'stream' => $streaming
        ];

        // Add reasoning effort for supported models (not for gpt-5 with minimal which doesn't support web search)
        // Per OpenAI docs: web search is not supported with gpt-5 minimal reasoning
        $is_gpt5_model = strpos($selected_model, 'gpt-5') === 0;
        if ($is_gpt5_model && $selected_model !== 'gpt-5.2') {
            // Use 'low' for GPT-5.1, skip for others to avoid 'minimal' which doesn't support web search
            if ($selected_model === 'gpt-5.1-2025-11-13') {
                $request_body['reasoning'] = ['effort' => 'low'];
            }
            // For other GPT-5 models, don't set reasoning to allow web search
        }

        error_log("MXCHAT WEB SEARCH: Request body: " . json_encode($request_body));

        if ($streaming) {
            return $this->mxchat_web_search_streaming_response($request_body, $api_key, $session_id, $testing_data);
        } else {
            return $this->mxchat_web_search_non_streaming_response($request_body, $api_key, $session_id, $testing_data);
        }

    } catch (Exception $e) {
        error_log("MXCHAT WEB SEARCH ERROR: " . $e->getMessage());
        return [
            'error' => sprintf(esc_html__('Web search error: %s', 'mxchat'), esc_html($e->getMessage())),
            'error_code' => 'web_search_exception'
        ];
    }
}

/**
 * Handle non-streaming web search response
 */
private function mxchat_web_search_non_streaming_response($request_body, $api_key, $session_id, $testing_data) {
    $request_body['stream'] = false;

    $response = wp_remote_post('https://api.openai.com/v1/responses', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($request_body),
        'timeout' => 90 // Web search can take longer
    ));

    if (is_wp_error($response)) {
        error_log("MXCHAT WEB SEARCH ERROR: WP Error: " . $response->get_error_message());
        return [
            'error' => esc_html__('Failed to connect to OpenAI web search API', 'mxchat'),
            'error_code' => 'web_search_connection_error'
        ];
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    error_log("MXCHAT WEB SEARCH: Response code: " . $response_code);
    error_log("MXCHAT WEB SEARCH: Response body (first 2000): " . substr($response_body, 0, 2000));

    if ($response_code !== 200) {
        $error_data = json_decode($response_body, true);
        $error_message = $error_data['error']['message'] ?? 'Unknown API error';
        return [
            'error' => sprintf(esc_html__('OpenAI API error: %s', 'mxchat'), esc_html($error_message)),
            'error_code' => 'web_search_api_error'
        ];
    }

    $result = json_decode($response_body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'error' => esc_html__('Invalid response from OpenAI', 'mxchat'),
            'error_code' => 'web_search_json_error'
        ];
    }

    // Extract the response text and citations from Responses API format
    $output_text = '';
    $citations = [];

    if (isset($result['output'])) {
        foreach ($result['output'] as $output_item) {
            if ($output_item['type'] === 'message' && isset($output_item['content'])) {
                foreach ($output_item['content'] as $content_item) {
                    if ($content_item['type'] === 'output_text') {
                        $output_text .= $content_item['text'];

                        // Extract citations/annotations
                        if (isset($content_item['annotations'])) {
                            foreach ($content_item['annotations'] as $annotation) {
                                if ($annotation['type'] === 'url_citation') {
                                    $citations[] = [
                                        'url' => $annotation['url'],
                                        'title' => $annotation['title'] ?? ''
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // If we have citations, append them to the response
    if (!empty($citations)) {
        $output_text .= "\n\n**Sources:**\n";
        $seen_urls = [];
        foreach ($citations as $citation) {
            if (!in_array($citation['url'], $seen_urls)) {
                $seen_urls[] = $citation['url'];
                $title = !empty($citation['title']) ? $citation['title'] : $citation['url'];
                $output_text .= "- [" . $title . "](" . $citation['url'] . ")\n";
            }
        }
    }

    // Save to transcript
    if (!empty($output_text) && !empty($session_id)) {
        $this->mxchat_save_chat_message($session_id, 'bot', $output_text);
    }

    return $output_text;
}

/**
 * Handle streaming web search response using Responses API
 */
private function mxchat_web_search_streaming_response($request_body, $api_key, $session_id, $testing_data) {
    $request_body['stream'] = true;

    // Check if we can stream
    if (headers_sent() || !function_exists('curl_init')) {
        // Fallback to non-streaming
        return $this->mxchat_web_search_non_streaming_response($request_body, $api_key, $session_id, $testing_data);
    }

    // Setup streaming headers
    $this->setup_streaming_headers();

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/responses');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Web search can take longer

    $full_response = '';
    $stream_started = false;
    $buffer = '';
    $citations = [];

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$full_response, &$stream_started, &$buffer, &$citations, $testing_data) {
        // Send testing data as first event if available
        if (!$stream_started && $testing_data !== null) {
            echo "data: " . json_encode(['testing_data' => $testing_data]) . "\n\n";
            flush();
            $stream_started = true;
        }

        $buffer .= $data;
        $lines = explode("\n", $buffer);
        $buffer = array_pop($lines);

        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            if (strpos($line, 'data: ') !== 0) continue;

            $json_str = substr($line, 6);

            if (trim($json_str) === '[DONE]') {
                // Append citations if we have any
                if (!empty($citations)) {
                    $citation_text = "\n\n**Sources:**\n";
                    $seen_urls = [];
                    foreach ($citations as $citation) {
                        if (!in_array($citation['url'], $seen_urls)) {
                            $seen_urls[] = $citation['url'];
                            $title = !empty($citation['title']) ? $citation['title'] : $citation['url'];
                            $citation_text .= "- [" . $title . "](" . $citation['url'] . ")\n";
                        }
                    }
                    echo "data: " . json_encode(['content' => $citation_text]) . "\n\n";
                    $full_response .= $citation_text;
                    flush();
                }
                echo "data: [DONE]\n\n";
                flush();
                continue;
            }

            $json = json_decode(trim($json_str), true);
            if (!$json) continue;

            // Handle Responses API streaming events
            // The format is different from Chat Completions
            if (isset($json['type'])) {
                switch ($json['type']) {
                    case 'response.output_text.delta':
                        // Text content delta
                        if (isset($json['delta'])) {
                            $content = $json['delta'];
                            $full_response .= $content;
                            echo "data: " . json_encode(['content' => $content]) . "\n\n";
                            flush();
                        }
                        break;

                    case 'response.output_item.done':
                        // Check for citations in completed items
                        if (isset($json['item']['content'])) {
                            foreach ($json['item']['content'] as $content_item) {
                                if (isset($content_item['annotations'])) {
                                    foreach ($content_item['annotations'] as $annotation) {
                                        if ($annotation['type'] === 'url_citation') {
                                            $citations[] = [
                                                'url' => $annotation['url'],
                                                'title' => $annotation['title'] ?? ''
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                        break;
                }
            }
        }

        return strlen($data);
    });

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch) || $http_code !== 200) {
        $curl_error = curl_error($ch);
        curl_close($ch);

        error_log("MXCHAT WEB SEARCH STREAM ERROR: HTTP $http_code, cURL error: $curl_error");

        // Fallback to non-streaming
        $fallback_response = $this->mxchat_web_search_non_streaming_response($request_body, $api_key, $session_id, $testing_data);

        if (is_array($fallback_response) && isset($fallback_response['error'])) {
            echo "data: " . json_encode([
                'error' => true,
                'error_message' => $fallback_response['error'],
                'error_code' => $fallback_response['error_code'] ?? 'web_search_error'
            ]) . "\n\n";
            echo "data: [DONE]\n\n";
            flush();
            return true;
        }

        $response_data = [
            'text' => $fallback_response,
            'html' => '',
            'session_id' => $session_id
        ];
        if ($testing_data !== null) {
            $response_data['testing_data'] = $testing_data;
        }
        header('Content-Type: application/json');
        echo json_encode($response_data);
        return true;
    }

    curl_close($ch);

    // Save the complete response
    if (!empty($full_response) && !empty($session_id)) {
        $this->mxchat_save_chat_message($session_id, 'bot', $full_response);
    }

    return true;
}

private function mxchat_generate_response_claude_stream($selected_model, $claude_api_key, $conversation_history, $relevant_content, $session_id, $testing_data = null) {
    try {
        // Get bot ID from session or request
        $bot_id = $this->get_current_bot_id($session_id);
        
        // Get system prompt instructions using centralized function
        $system_prompt_instructions = $this->get_system_instructions($bot_id, $session_id);
        // Ensure conversation_history is an array
        if (!is_array($conversation_history)) {
            $conversation_history = array();
        }

        // Clean and validate conversation history
        foreach ($conversation_history as &$message) {
            // Convert bot and agent roles to assistant
            if ($message['role'] === 'bot' || $message['role'] === 'agent') {
                $message['role'] = 'assistant';
            }
            
            // Remove unsupported roles - Claude only supports 'assistant' and 'user'
            if (!in_array($message['role'], ['assistant', 'user'])) {
                $message['role'] = 'user';
            }

            // Ensure content field exists
            if (!isset($message['content']) || empty($message['content'])) {
                $message['content'] = '';
            }

            // Remove any unsupported fields
            $message = array_intersect_key($message, array_flip(['role', 'content']));
        }

        // Add relevant content as the latest user message
        $conversation_history[] = [
            'role' => 'user',
            'content' => $relevant_content
        ];

        // Prepare the request body with stream: true
        $body = json_encode([
            'model' => $selected_model,
            'messages' => $conversation_history,
            'max_tokens' => 1000,
            'temperature' => 0.8,
            'system' => $system_prompt_instructions,
            'stream' => true
        ]);

        // Check if we can actually stream (headers not sent, etc.)
        if (headers_sent() || !function_exists('curl_init')) {
            // Fallback to regular response with testing data
            //error_log("MxChat: Streaming not possible, falling back to regular response");
            $regular_response = $this->mxchat_generate_response_claude(
                $selected_model,
                $claude_api_key,
                array_slice($conversation_history, 0, -1), // Remove the added content
                $relevant_content
            );
            
            // Save bot response to transcript
            if (!empty($regular_response) && !empty($session_id)) {
                $this->mxchat_save_chat_message($session_id, 'bot', $regular_response);
            }
            
            // Return as JSON with testing data
            $response_data = [
                'text' => $regular_response,
                'html' => '',
                'session_id' => $session_id
            ];
            
            if ($testing_data !== null) {
                $response_data['testing_data'] = $testing_data;
                //error_log("MxChat Testing: Added testing data to Claude fallback response");
            }
            
            // Clear any streaming headers and send JSON
            if (headers_sent() === false) {
                header('Content-Type: application/json');
            }
            echo json_encode($response_data);
            return true; // Indicate we handled the response
        }

        // Setup streaming headers now that we know we're actually streaming
        $this->setup_streaming_headers();

        // Use cURL for streaming support
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.anthropic.com/v1/messages');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'x-api-key: ' . $claude_api_key,
            'anthropic-version: 2023-06-01'
        ));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $full_response = ''; // Accumulate full response for saving
        $stream_started = false;
        $buffer = ''; // CRITICAL: Add persistent buffer for incomplete chunks

        // Buffer control for real-time streaming
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$full_response, &$stream_started, &$buffer, $testing_data) {
            // Send testing data as the first event if available
            if (!$stream_started && $testing_data !== null) {
                echo "data: " . json_encode(['testing_data' => $testing_data]) . "\n\n";
                flush();
                $stream_started = true;
                //error_log("MxChat Testing: Sent testing data in Claude stream");
            }
            
            // CRITICAL FIX: Append new data to buffer
            $buffer .= $data;
            
            // Process complete lines only
            $lines = explode("\n", $buffer);
            
            // CRITICAL FIX: Keep the last incomplete line in the buffer
            // The last element might be incomplete, so keep it in buffer
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                if (trim($line) === '') {
                    continue;
                }

                // Claude uses event: and data: format
                if (strpos($line, 'event: ') === 0) {
                    // Store the event type for the next data line
                    continue;
                }

                if (strpos($line, 'data: ') === 0) {
                    $json_str = substr($line, 6); // Remove 'data: ' prefix

                    $json = json_decode(trim($json_str), true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        continue;
                    }

                    // Handle different event types
                    if (isset($json['type'])) {
                        switch ($json['type']) {
                            case 'content_block_delta':
                                if (isset($json['delta']['text'])) {
                                    $content = $json['delta']['text'];
                                    $full_response .= $content; // Accumulate
                                    // Send as SSE format compatible with your frontend
                                    echo "data: " . json_encode(['content' => $content]) . "\n\n";
                                    flush();
                                }
                                break;

                            case 'message_stop':
                                echo "data: [DONE]\n\n";
                                flush();
                                break;

                            case 'error':
                                echo "data: " . json_encode(['error' => $json['error']['message'] ?? 'Unknown error']) . "\n\n";
                                flush();
                                break;
                        }
                    }
                }
            }

            return strlen($data);
        });

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            curl_close($ch);
            throw new Exception('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        if ($http_code !== 200) {
            // Fallback to regular response
            //error_log("MxChat: Claude streaming failed with HTTP $http_code, falling back");
            $regular_response = $this->mxchat_generate_response_claude(
                $selected_model,
                $claude_api_key,
                array_slice($conversation_history, 0, -1), // Remove the added content
                $relevant_content
            );

            // FIXED: Check if regular response returned an error
            if (is_array($regular_response) && isset($regular_response['error'])) {
                // Send error in SSE format since we're in streaming mode
                echo "data: " . json_encode([
                    'error' => true,
                    'error_message' => $regular_response['error'],
                    'error_code' => $regular_response['error_code'] ?? 'api_error',
                    'text' => $regular_response['error'],
                    'message' => $regular_response['error']
                ]) . "\n\n";
                echo "data: [DONE]\n\n";
                flush();
                return true;
            }

            $response_data = [
                'text' => $regular_response,
                'html' => '',
                'session_id' => $session_id
            ];

            if ($testing_data !== null) {
                $response_data['testing_data'] = $testing_data;
                //error_log("MxChat Testing: Added testing data to Claude error fallback");
            }

            header('Content-Type: application/json');
            echo json_encode($response_data);
            return true;
        }

        // Save the complete response to maintain chat persistence
        if (!empty($full_response) && !empty($session_id)) {
            // Prepare RAG context for streaming response
            $rag_context_for_storage = null;
            $has_rag_data = $this->last_similarity_analysis !== null && !empty($this->last_similarity_analysis['top_matches']);
            $has_action_data = isset($this->last_action_analysis) && !empty($this->last_action_analysis);

            if ($has_rag_data || $has_action_data) {
                $rag_context_for_storage = [];

                if ($has_rag_data) {
                    $rag_context_for_storage['top_matches'] = $this->last_similarity_analysis['top_matches'];
                    $rag_context_for_storage['approved_urls'] = $this->current_valid_urls ?? [];
                    $rag_context_for_storage['similarity_threshold'] = $this->last_similarity_analysis['threshold_used'] ?? 0.35;
                    $rag_context_for_storage['knowledge_base_type'] = $this->last_similarity_analysis['knowledge_base_type'] ?? 'WordPress Database';
                    $rag_context_for_storage['total_documents_checked'] = $this->last_similarity_analysis['total_checked'] ?? 0;
                }

                if ($has_action_data) {
                    $rag_context_for_storage['action_analysis'] = $this->last_action_analysis;
                }
            }
            $this->mxchat_save_chat_message($session_id, 'bot', $full_response, null, $rag_context_for_storage);
        }

        return true; // Indicate streaming completed successfully

    } catch (Exception $e) {
        //error_log("MxChat Claude streaming exception: " . $e->getMessage());

        // Fallback to regular response on exception
        $regular_response = $this->mxchat_generate_response_claude(
            $selected_model,
            $claude_api_key,
            $conversation_history,
            $relevant_content
        );

        // FIXED: Check if regular response returned an error
        if (is_array($regular_response) && isset($regular_response['error'])) {
            // Send error in SSE format since we're in streaming mode
            echo "data: " . json_encode([
                'error' => true,
                'error_message' => $regular_response['error'],
                'error_code' => $regular_response['error_code'] ?? 'api_error',
                'text' => $regular_response['error'],
                'message' => $regular_response['error']
            ]) . "\n\n";
            echo "data: [DONE]\n\n";
            flush();
            return true;
        }

        $response_data = [
            'text' => $regular_response,
            'html' => '',
            'session_id' => $session_id
        ];

        if ($testing_data !== null) {
            $response_data['testing_data'] = $testing_data;
            //error_log("MxChat Testing: Added testing data to Claude exception fallback");
        }

        header('Content-Type: application/json');
        echo json_encode($response_data);
        return true;
    }
}
private function mxchat_generate_response_xai_stream($selected_model, $xai_api_key, $conversation_history, $relevant_content, $session_id, $testing_data = null) {
    try {
        // Get bot ID from session or request
        $bot_id = $this->get_current_bot_id($session_id);
        
        // Get system prompt instructions using centralized function
        $system_prompt_instructions = $this->get_system_instructions($bot_id, $session_id);
        
        // Ensure conversation_history is an array
        if (!is_array($conversation_history)) {
            $conversation_history = array();
        }

        // Format conversation history for X.AI (same as OpenAI format)
        $formatted_conversation = array();

        $formatted_conversation[] = array(
            'role' => 'system',
            'content' => $system_prompt_instructions . " " . $relevant_content
        );

        foreach ($conversation_history as $message) {
            if (is_array($message) && isset($message['role']) && isset($message['content'])) {
                $role = $message['role'];
                if ($role === 'bot' || $role === 'agent') {
                    $role = 'assistant';
                }
                if (!in_array($role, ['system', 'assistant', 'user', 'function', 'tool'])) {
                    $role = 'user';
                }
                $formatted_conversation[] = array(
                    'role' => $role,
                    'content' => $message['content']
                );
            }
        }

        // Check if we can actually stream
        if (headers_sent() || !function_exists('curl_init')) {
            // Fallback to regular response with testing data
            //error_log("MxChat: X.AI streaming not possible, falling back to regular response");
            $regular_response = $this->mxchat_generate_response_xai(
                $selected_model,
                $xai_api_key,
                $conversation_history,
                $relevant_content
            );
            
            // Save bot response to transcript
            if (!empty($regular_response) && !empty($session_id)) {
                $this->mxchat_save_chat_message($session_id, 'bot', $regular_response);
            }
            
            $response_data = [
                'text' => $regular_response,
                'html' => '',
                'session_id' => $session_id
            ];
            
            if ($testing_data !== null) {
                $response_data['testing_data'] = $testing_data;
                //error_log("MxChat Testing: Added testing data to X.AI fallback response");
            }
            
            header('Content-Type: application/json');
            echo json_encode($response_data);
            return true;
        }

        // Prepare the request body with stream: true
        $body = json_encode([
            'model' => $selected_model,
            'messages' => $formatted_conversation,
            'temperature' => 0.8,
            'stream' => true
        ]);

        // Setup streaming headers now that we know we're actually streaming
        $this->setup_streaming_headers();

        // Use cURL for streaming support
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.x.ai/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $xai_api_key
        ));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $full_response = ''; // Accumulate full response for saving
        $stream_started = false;
        $buffer = ''; // CRITICAL: Add persistent buffer for incomplete chunks
        
        // Buffer control for real-time streaming
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$full_response, &$stream_started, &$buffer, $testing_data) {
            // Send testing data as the first event if available
            if (!$stream_started && $testing_data !== null) {
                echo "data: " . json_encode(['testing_data' => $testing_data]) . "\n\n";
                flush();
                $stream_started = true;
                //error_log("MxChat Testing: Sent testing data in X.AI stream");
            }
            
            // CRITICAL FIX: Append new data to buffer
            $buffer .= $data;
            
            // Process complete lines only
            $lines = explode("\n", $buffer);
            
            // CRITICAL FIX: Keep the last incomplete line in the buffer
            // The last element might be incomplete, so keep it in buffer
            $buffer = array_pop($lines);
            
            foreach ($lines as $line) {
                // Skip empty lines
                if (trim($line) === '') {
                    continue;
                }
                
                // Only process lines that start with "data: "
                if (strpos($line, 'data: ') !== 0) {
                    continue;
                }
                
                $json_str = substr($line, 6); // Remove 'data: ' prefix
                
                if (trim($json_str) === '[DONE]') {
                    echo "data: [DONE]\n\n";
                    flush();
                    continue;
                }
                
                // Try to decode JSON
                $json = json_decode(trim($json_str), true);
                if ($json && isset($json['choices'][0]['delta']['content'])) {
                    $content = $json['choices'][0]['delta']['content'];
                    $full_response .= $content; // Accumulate
                    // Send as SSE format
                    echo "data: " . json_encode(['content' => $content]) . "\n\n";
                    flush();
                }
            }
            
            return strlen($data);
        });
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch) || $http_code !== 200) {
            curl_close($ch);
            
            // Fallback to regular response
            //error_log("MxChat: X.AI streaming failed, falling back");
            $regular_response = $this->mxchat_generate_response_xai(
                $selected_model,
                $xai_api_key,
                $conversation_history,
                $relevant_content
            );
            
            $response_data = [
                'text' => $regular_response,
                'html' => '',
                'session_id' => $session_id
            ];
            
            if ($testing_data !== null) {
                $response_data['testing_data'] = $testing_data;
                //error_log("MxChat Testing: Added testing data to X.AI error fallback");
            }
            
            header('Content-Type: application/json');
            echo json_encode($response_data);
            return true;
        }
        
        curl_close($ch);

        // Save the complete response to maintain chat persistence
        if (!empty($full_response) && !empty($session_id)) {
            // Prepare RAG context for streaming response
            $rag_context_for_storage = null;
            $has_rag_data = $this->last_similarity_analysis !== null && !empty($this->last_similarity_analysis['top_matches']);
            $has_action_data = isset($this->last_action_analysis) && !empty($this->last_action_analysis);

            if ($has_rag_data || $has_action_data) {
                $rag_context_for_storage = [];

                if ($has_rag_data) {
                    $rag_context_for_storage['top_matches'] = $this->last_similarity_analysis['top_matches'];
                    $rag_context_for_storage['approved_urls'] = $this->current_valid_urls ?? [];
                    $rag_context_for_storage['similarity_threshold'] = $this->last_similarity_analysis['threshold_used'] ?? 0.35;
                    $rag_context_for_storage['knowledge_base_type'] = $this->last_similarity_analysis['knowledge_base_type'] ?? 'WordPress Database';
                    $rag_context_for_storage['total_documents_checked'] = $this->last_similarity_analysis['total_checked'] ?? 0;
                }

                if ($has_action_data) {
                    $rag_context_for_storage['action_analysis'] = $this->last_action_analysis;
                }
            }
            $this->mxchat_save_chat_message($session_id, 'bot', $full_response, null, $rag_context_for_storage);
        }

        return true; // Indicate streaming completed successfully

    } catch (Exception $e) {
        //error_log("MxChat X.AI streaming exception: " . $e->getMessage());

        // Fallback to regular response
        $regular_response = $this->mxchat_generate_response_xai(
            $selected_model,
            $xai_api_key,
            $conversation_history,
            $relevant_content
        );
        
        $response_data = [
            'text' => $regular_response,
            'html' => '',
            'session_id' => $session_id
        ];
        
        if ($testing_data !== null) {
            $response_data['testing_data'] = $testing_data;
            //error_log("MxChat Testing: Added testing data to X.AI exception fallback");
        }
        
        header('Content-Type: application/json');
        echo json_encode($response_data);
        return true;
    }
}
private function mxchat_generate_response_deepseek_stream($selected_model, $deepseek_api_key, $conversation_history, $relevant_content, $session_id, $testing_data = null) {
    try {
        // Get bot ID from session or request
        $bot_id = $this->get_current_bot_id($session_id);
        
        // Get system prompt instructions using centralized function
        $system_prompt_instructions = $this->get_system_instructions($bot_id, $session_id);
        
        // Ensure conversation_history is an array
        if (!is_array($conversation_history)) {
            $conversation_history = array();
        }

        // Format conversation history for DeepSeek
        $formatted_conversation = array();

        $formatted_conversation[] = array(
            'role' => 'system',
            'content' => $system_prompt_instructions . " " . $relevant_content
        );

        foreach ($conversation_history as $message) {
            if (is_array($message) && isset($message['role']) && isset($message['content'])) {
                $role = $message['role'];
                if ($role === 'bot' || $role === 'agent') {
                    $role = 'assistant';
                }
                if (!in_array($role, ['system', 'assistant', 'user', 'function', 'tool'])) {
                    $role = 'user';
                }
                $formatted_conversation[] = array(
                    'role' => $role,
                    'content' => $message['content']
                );
            }
        }

        // Check if we can actually stream
        if (headers_sent() || !function_exists('curl_init')) {
            // Fallback to regular response with testing data
            //error_log("MxChat: DeepSeek streaming not possible, falling back to regular response");
            $regular_response = $this->mxchat_generate_response_deepseek(
                $selected_model,
                $deepseek_api_key,
                $conversation_history,
                $relevant_content
            );
            
            // Save bot response to transcript
            if (!empty($regular_response) && !empty($session_id)) {
                $this->mxchat_save_chat_message($session_id, 'bot', $regular_response);
            }
            
            $response_data = [
                'text' => $regular_response,
                'html' => '',
                'session_id' => $session_id
            ];
            
            if ($testing_data !== null) {
                $response_data['testing_data'] = $testing_data;
                //error_log("MxChat Testing: Added testing data to DeepSeek fallback response");
            }
            
            header('Content-Type: application/json');
            echo json_encode($response_data);
            return true;
        }

        // Prepare the request body with stream: true
        $body = json_encode([
            'model' => $selected_model,
            'messages' => $formatted_conversation,
            'temperature' => 0.8,
            'stream' => true
        ]);

        // Setup streaming headers now that we know we're actually streaming
        $this->setup_streaming_headers();

        // Use cURL for streaming support
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.deepseek.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $deepseek_api_key
        ));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $full_response = ''; // Accumulate full response for saving
        $stream_started = false;
        $buffer = ''; // CRITICAL: Add persistent buffer for incomplete chunks
        
        // Buffer control for real-time streaming
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$full_response, &$stream_started, &$buffer, $testing_data) {
            // Send testing data as the first event if available
            if (!$stream_started && $testing_data !== null) {
                echo "data: " . json_encode(['testing_data' => $testing_data]) . "\n\n";
                flush();
                $stream_started = true;
                //error_log("MxChat Testing: Sent testing data in DeepSeek stream");
            }
            
            // CRITICAL FIX: Append new data to buffer
            $buffer .= $data;
            
            // Process complete lines only
            $lines = explode("\n", $buffer);
            
            // CRITICAL FIX: Keep the last incomplete line in the buffer
            // The last element might be incomplete, so keep it in buffer
            $buffer = array_pop($lines);
            
            foreach ($lines as $line) {
                // Skip empty lines
                if (trim($line) === '') {
                    continue;
                }
                
                // Only process lines that start with "data: "
                if (strpos($line, 'data: ') !== 0) {
                    continue;
                }
                
                $json_str = substr($line, 6); // Remove 'data: ' prefix
                
                if (trim($json_str) === '[DONE]') {
                    echo "data: [DONE]\n\n";
                    flush();
                    continue;
                }
                
                // Try to decode JSON
                $json = json_decode(trim($json_str), true);
                if ($json && isset($json['choices'][0]['delta']['content'])) {
                    $content = $json['choices'][0]['delta']['content'];
                    $full_response .= $content; // Accumulate the full response
                    
                    // Send as SSE format
                    echo "data: " . json_encode(['content' => $content]) . "\n\n";
                    flush();
                }
            }
            
            return strlen($data);
        });
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch) || $http_code !== 200) {
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            // Log the specific error for debugging
            //error_log("MxChat: DeepSeek streaming failed - HTTP: $http_code, cURL: $curl_error");
            
            // Fallback to regular response
            $regular_response = $this->mxchat_generate_response_deepseek(
                $selected_model,
                $deepseek_api_key,
                $conversation_history,
                $relevant_content
            );
            
            // Handle error response from regular function
            if (is_array($regular_response) && isset($regular_response['error'])) {
                if ($testing_data !== null) {
                    $regular_response['testing_data'] = $testing_data;
                }
                header('Content-Type: application/json');
                echo json_encode($regular_response);
                return true;
            }
            
            $response_data = [
                'text' => $regular_response,
                'html' => '',
                'session_id' => $session_id
            ];
            
            if ($testing_data !== null) {
                $response_data['testing_data'] = $testing_data;
                //error_log("MxChat Testing: Added testing data to DeepSeek error fallback");
            }
            
            header('Content-Type: application/json');
            echo json_encode($response_data);
            return true;
        }
        
        curl_close($ch);

        // Save the complete response to maintain chat persistence
        if (!empty($full_response) && !empty($session_id)) {
            // Prepare RAG context for streaming response
            $rag_context_for_storage = null;
            $has_rag_data = $this->last_similarity_analysis !== null && !empty($this->last_similarity_analysis['top_matches']);
            $has_action_data = isset($this->last_action_analysis) && !empty($this->last_action_analysis);

            if ($has_rag_data || $has_action_data) {
                $rag_context_for_storage = [];

                if ($has_rag_data) {
                    $rag_context_for_storage['top_matches'] = $this->last_similarity_analysis['top_matches'];
                    $rag_context_for_storage['approved_urls'] = $this->current_valid_urls ?? [];
                    $rag_context_for_storage['similarity_threshold'] = $this->last_similarity_analysis['threshold_used'] ?? 0.35;
                    $rag_context_for_storage['knowledge_base_type'] = $this->last_similarity_analysis['knowledge_base_type'] ?? 'WordPress Database';
                    $rag_context_for_storage['total_documents_checked'] = $this->last_similarity_analysis['total_checked'] ?? 0;
                }

                if ($has_action_data) {
                    $rag_context_for_storage['action_analysis'] = $this->last_action_analysis;
                }
            }
            $this->mxchat_save_chat_message($session_id, 'bot', $full_response, null, $rag_context_for_storage);
        }

        return true; // Indicate streaming completed successfully

    } catch (Exception $e) {
        //error_log("MxChat DeepSeek streaming exception: " . $e->getMessage());

        // Fallback to regular response
        $regular_response = $this->mxchat_generate_response_deepseek(
            $selected_model,
            $deepseek_api_key,
            $conversation_history,
            $relevant_content
        );
        
        // Handle error response from regular function
        if (is_array($regular_response) && isset($regular_response['error'])) {
            if ($testing_data !== null) {
                $regular_response['testing_data'] = $testing_data;
            }
            header('Content-Type: application/json');
            echo json_encode($regular_response);
            return true;
        }
        
        $response_data = [
            'text' => $regular_response,
            'html' => '',
            'session_id' => $session_id
        ];
        
        if ($testing_data !== null) {
            $response_data['testing_data'] = $testing_data;
            //error_log("MxChat Testing: Added testing data to DeepSeek exception fallback");
        }
        
        header('Content-Type: application/json');
        echo json_encode($response_data);
        return true;
    }
}


private function mxchat_generate_response_openrouter($selected_model, $openrouter_api_key, $conversation_history, $relevant_content) {
    try {
        if (!is_array($conversation_history)) {
            $conversation_history = array();
        }

        $bot_id = $this->get_current_bot_id('');
        $system_prompt_instructions = $this->get_system_instructions($bot_id, $session_id);
        
        $formatted_conversation = array();

        $formatted_conversation[] = array(
            'role' => 'system',
            'content' => $system_prompt_instructions . " " . $relevant_content
        );

        foreach ($conversation_history as $message) {
            if (is_array($message) && isset($message['role']) && isset($message['content'])) {
                $role = $message['role'];

                if ($role === 'bot' || $role === 'agent') {
                    $role = 'assistant';
                }
                if (!in_array($role, ['system', 'assistant', 'user'])) {
                    $role = 'user';
                }

                $formatted_conversation[] = array(
                    'role' => $role,
                    'content' => $message['content']
                );
            }
        }

        $body = json_encode([
            'model' => $selected_model,
            'messages' => $formatted_conversation,
            'temperature' => 1,
        ]);

        $args = [
            'body'        => $body,
            'headers'     => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $openrouter_api_key,
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name'),
            ],
            'timeout'     => 60,
            'redirection' => 5,
            'blocking'    => true,
            'httpversion' => '1.0',
            'sslverify'   => true,
        ];

        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            return [
                'error' => esc_html__('Connection error when contacting OpenRouter: ', 'mxchat') . esc_html($error_message),
                'error_code' => 'openrouter_connection_error',
                'provider' => 'openrouter'
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $response_body = wp_remote_retrieve_body($response);
            $decoded_response = json_decode($response_body, true);
            
            $error_message = isset($decoded_response['error']['message']) 
                ? $decoded_response['error']['message'] 
                : 'HTTP Error ' . $status_code;
            
            return [
                'error' => esc_html__('OpenRouter API error: ', 'mxchat') . esc_html($error_message),
                'error_code' => 'openrouter_api_error',
                'provider' => 'openrouter',
                'status_code' => $status_code
            ];
        }

        $response_body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($response_body, true);

        if (isset($decoded_response['choices'][0]['message']['content'])) {
            return trim($decoded_response['choices'][0]['message']['content']);
        } else {
            return [
                'error' => esc_html__('Unexpected response format from OpenRouter.', 'mxchat'),
                'error_code' => 'openrouter_response_format_error',
                'provider' => 'openrouter'
            ];
        }
    } catch (Exception $e) {
        return [
            'error' => esc_html__('System error when processing OpenRouter request: ', 'mxchat') . esc_html($e->getMessage()),
            'error_code' => 'openrouter_exception',
            'provider' => 'openrouter'
        ];
    }
}
private function mxchat_generate_response_claude($selected_model, $claude_api_key, $conversation_history, $relevant_content) {
    
        // Get bot ID from session or request
        $bot_id = $this->get_current_bot_id($session_id);
        
        // Get system prompt instructions using centralized function
        $system_prompt_instructions = $this->get_system_instructions($bot_id, $session_id);
        
    // Clean and validate conversation history
    foreach ($conversation_history as &$message) {
        // Convert bot and agent roles to assistant
        if ($message['role'] === 'bot' || $message['role'] === 'agent') {
            $message['role'] = 'assistant';
        }
        
        // Remove unsupported roles - Claude only supports 'assistant' and 'user'
        if (!in_array($message['role'], ['assistant', 'user'])) {
            $message['role'] = 'user';
        }

        // Ensure content field exists
        if (!isset($message['content']) || empty($message['content'])) {
            $message['content'] = '';
        }

        // Remove any unsupported fields
        $message = array_intersect_key($message, array_flip(['role', 'content']));
    }

    // Add relevant content as the latest user message
    $conversation_history[] = [
        'role' => 'user',
        'content' => $relevant_content
    ];

    // Build request body
    $body = json_encode([
        'model' => $selected_model,
        'max_tokens' => 1000,
        'temperature' => 0.8,
        'messages' => $conversation_history,
        'system' => $system_prompt_instructions
    ]);

    // Set up API request
    $args = [
        'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $claude_api_key,
                'anthropic-version' => '2023-06-01'
            ],
        'timeout' => 60,
        'redirection' => 5,
        'blocking' => true,
        'httpversion' => '1.0',
        'sslverify' => true,
    ];

    // Make API request
    $response = wp_remote_post('https://api.anthropic.com/v1/messages', $args);

    // Check for WordPress errors
    if (is_wp_error($response)) {
        //error_log("Claude API request error: " . $response->get_error_message());
        return "Sorry, there was an error connecting to the API.";
    }

    // Check HTTP response code
    $http_code = wp_remote_retrieve_response_code($response);
    if ($http_code !== 200) {
        $error_body = wp_remote_retrieve_body($response);
        //error_log("Claude API HTTP error: " . $http_code . " - " . $error_body);
        
        // Try to extract error message from response
        $error_data = json_decode($error_body, true);
        $error_message = isset($error_data['error']['message']) ? 
            $error_data['error']['message'] : 
            "HTTP error " . $http_code;
            
        return "Sorry, the API returned an error: " . $error_message;
    }

    // Parse response
    $response_body = json_decode(wp_remote_retrieve_body($response), true);
    
    // Check for JSON decode errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        //error_log("Claude API JSON decode error: " . json_last_error_msg());
        return "Sorry, there was an error processing the API response.";
    }

    // Extract and validate response content
    if (isset($response_body['content']) && 
        is_array($response_body['content']) && 
        !empty($response_body['content']) && 
        isset($response_body['content'][0]['text'])) {
        return trim($response_body['content'][0]['text']);
    }

    // Log unexpected response format
    //error_log("Claude API unexpected response format: " . print_r($response_body, true));
    return "Sorry, I received an unexpected response format from the API.";
}
private function mxchat_generate_response_openai($selected_model, $api_key, $conversation_history, $relevant_content) {
    try {
        // Ensure conversation_history is an array
        if (!is_array($conversation_history)) {
            $conversation_history = array();
        }

        // Get bot ID from session or request
        $bot_id = $this->get_current_bot_id('');
        
        // Get system prompt instructions using centralized function
        $system_prompt_instructions = $this->get_system_instructions($bot_id, $session_id);
        
        // Create a new array for the formatted conversation
        $formatted_conversation = array();

        // Add system message first
        $formatted_conversation[] = array(
            'role' => 'system',
            'content' => $system_prompt_instructions . " " . $relevant_content
        );

        // Add the rest of the conversation history
        foreach ($conversation_history as $message) {
            if (is_array($message) && isset($message['role']) && isset($message['content'])) {
                $role = $message['role'];

                // Convert roles to supported format
                if ($role === 'bot' || $role === 'agent') {
                    $role = 'assistant';
                }
                if (!in_array($role, ['system', 'assistant', 'user', 'function', 'tool'])) {
                    $role = 'user';
                }

                $formatted_conversation[] = array(
                    'role' => $role,
                    'content' => $message['content']
                );
            }
        }

        // Check if this is a GPT-5 model (supports reasoning_effort parameter)
        $is_gpt5_model = (
            strpos($selected_model, 'gpt-5') === 0 ||
            $selected_model === 'gpt-5.2' ||
            $selected_model === 'gpt-5.1-2025-11-13' ||
            $selected_model === 'gpt-5' ||
            $selected_model === 'gpt-5-mini' ||
            $selected_model === 'gpt-5-nano'
        );

        // Build request body with optimal settings for fast responses
        $request_body = [
            'model' => $selected_model,
            'messages' => $formatted_conversation,
            'temperature' => 1,
            'stream' => false
        ];

        // Add reasoning_effort only for GPT-5 models that support it
        // gpt-5.2 and gpt-5.1-chat-latest don't support reasoning_effort parameter
        if ($is_gpt5_model && $selected_model !== 'gpt-5.2' && $selected_model !== 'gpt-5.1-chat-latest') {
            // GPT-5.1 uses 'low' instead of 'minimal'
            if ($selected_model === 'gpt-5.1-2025-11-13') {
                $request_body['reasoning_effort'] = 'low';
            } else {
                $request_body['reasoning_effort'] = 'minimal'; // For other GPT-5 models
            }
        }

        $body = json_encode($request_body);

        $args = [
            'body'        => $body,
            'headers'     => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'timeout'     => 60,
            'redirection' => 5,
            'blocking'    => true,
            'httpversion' => '1.0',
            'sslverify'   => true,
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            return [
                'error' => esc_html__('Connection error when contacting OpenAI: ', 'mxchat') . esc_html($error_message),
                'error_code' => 'openai_connection_error',
                'provider' => 'openai'
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $response_body = wp_remote_retrieve_body($response);
            $decoded_response = json_decode($response_body, true);
            
            $error_message = isset($decoded_response['error']['message']) 
                ? $decoded_response['error']['message'] 
                : 'HTTP Error ' . $status_code;
            
            $error_type = isset($decoded_response['error']['type']) 
                ? $decoded_response['error']['type'] 
                : 'unknown';
            
            // Handle specific error types
            switch ($error_type) {
                case 'invalid_request_error':
                    if (strpos($error_message, 'API key') !== false) {
                        return [
                            'error' => esc_html__('Invalid OpenAI API key. Please check your API key configuration.', 'mxchat'),
                            'error_code' => 'openai_invalid_api_key',
                            'provider' => 'openai'
                        ];
                    }
                    break;
                    
                case 'authentication_error':
                    return [
                        'error' => esc_html__('Authentication failed with OpenAI. Please check your API key.', 'mxchat'),
                        'error_code' => 'openai_auth_error',
                        'provider' => 'openai'
                    ];
                
                case 'rate_limit_exceeded':
                    return [
                        'error' => esc_html__('OpenAI rate limit exceeded. Please try again later.', 'mxchat'),
                        'error_code' => 'openai_rate_limit',
                        'provider' => 'openai'
                    ];
                    
                case 'quota_exceeded':
                    return [
                        'error' => esc_html__('OpenAI API quota exceeded. Please check your billing details.', 'mxchat'),
                        'error_code' => 'openai_quota_exceeded',
                        'provider' => 'openai'
                    ];
            }
            
            // Generic error fallback
            return [
                'error' => esc_html__('OpenAI API error: ', 'mxchat') . esc_html($error_message),
                'error_code' => 'openai_api_error',
                'provider' => 'openai',
                'status_code' => $status_code
            ];
        }

        $response_body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($response_body, true);

        if (isset($decoded_response['choices'][0]['message']['content'])) {
            return trim($decoded_response['choices'][0]['message']['content']);
        } else {
            return [
                'error' => esc_html__('Unexpected response format from OpenAI.', 'mxchat'),
                'error_code' => 'openai_response_format_error',
                'provider' => 'openai'
            ];
        }
    } catch (Exception $e) {
        return [
            'error' => esc_html__('System error when processing OpenAI request: ', 'mxchat') . esc_html($e->getMessage()),
            'error_code' => 'openai_exception',
            'provider' => 'openai'
        ];
    }
}

private function mxchat_generate_response_xai($selected_model, $xai_api_key, $conversation_history, $relevant_content) {
    try {
        // Get bot ID from session or request
        $bot_id = $this->get_current_bot_id($session_id);
        
        // Get system prompt instructions using centralized function
        $system_prompt_instructions = $this->get_system_instructions($bot_id, $session_id);
        
        // Add system prompt to relevant content
    $content_with_instructions = $system_prompt_instructions . " " . $relevant_content;

    // Prepend system instructions to the conversation history
    array_unshift($conversation_history, [
        'role' => 'system',
        'content' => "Here are your instructions: " . $content_with_instructions
    ]);

    // Ensure consistency: Replace 'bot' and 'agent' roles with supported values
    foreach ($conversation_history as &$message) {
        if ($message['role'] === 'bot') {
            $message['role'] = 'assistant';
        } elseif ($message['role'] === 'agent') {
            // Tag the message as coming from a live agent
            $message['role'] = 'assistant';
            if (!isset($message['metadata'])) {
                $message['metadata'] = ['source' => 'live_agent'];
            }
        }

        // Ensure all roles are valid
        if (!in_array($message['role'], ['system', 'assistant', 'user', 'function', 'tool'])) {
            $message['role'] = 'user'; // Default to 'user'
        }
    }

    // Build the request body
    $body = json_encode([
        'model' => $selected_model,
        'messages' => $conversation_history,
        'temperature' => 0.8,
        'stream' => false
    ]);

    // Set up the API request
    $args = [
        'body'        => $body,
        'headers'     => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $xai_api_key,
        ],
        'timeout'     => 60,
        'redirection' => 5,
        'blocking'    => true,
        'httpversion' => '1.0',
        'sslverify'   => true,
    ];

    // Make the API request
    $response = wp_remote_post('https://api.x.ai/v1/chat/completions', $args);

    // Process the response
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        //error_log('X.AI API Error: ' . $error_message);
        return [
            'error' => esc_html__('Connection error when contacting X.AI: ', 'mxchat') . esc_html($error_message),
            'error_code' => 'xai_connection_error',
            'provider' => 'xai'
        ];
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        $response_body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($response_body, true);

        // Log the full response for debugging
        //error_log('X.AI Error Response: ' . print_r($decoded_response, true));

        // Extract error message from X.AI's specific format
        $error_message = '';
        
        // Check for direct error string (as seen in your logs)
        if (isset($decoded_response['error']) && is_string($decoded_response['error'])) {
            $error_message = $decoded_response['error'];
        }
        // Check for nested error object (OpenAI style)
        elseif (isset($decoded_response['error']['message'])) {
            $error_message = $decoded_response['error']['message'];
        }
        // Check for top-level message
        elseif (isset($decoded_response['message'])) {
            $error_message = $decoded_response['message'];
        }
        // Fallback
        else {
            $error_message = 'HTTP Error ' . $status_code;
        }

        //error_log('X.AI API HTTP Error: ' . $status_code . ' - ' . $error_message);

        // Check for API key errors using string matching
        if (stripos($error_message, 'api key') !== false || 
            stripos($error_message, 'incorrect api key') !== false ||
            stripos($error_message, 'invalid api key') !== false) {
            return [
                'error' => esc_html__('Invalid X.AI API key. Please check your API key configuration.', 'mxchat'),
                'error_code' => 'xai_invalid_api_key',
                'provider' => 'xai'
            ];
        }

        // Authentication errors
        if ($status_code === 401 || $status_code === 403 || 
            stripos($error_message, 'auth') !== false) {
            return [
                'error' => esc_html__('Authentication failed with X.AI. Please check your API key.', 'mxchat'),
                'error_code' => 'xai_auth_error',
                'provider' => 'xai'
            ];
        }

        // Model errors
        if (stripos($error_message, 'model') !== false) {
            return [
                'error' => esc_html__('Invalid model specified for X.AI. Please check your model configuration.', 'mxchat'),
                'error_code' => 'xai_invalid_model',
                'provider' => 'xai'
            ];
        }

        // Rate limit errors
        if ($status_code === 429 || 
            stripos($error_message, 'rate') !== false || 
            stripos($error_message, 'limit') !== false) {
            return [
                'error' => esc_html__('X.AI rate limit exceeded. Please try again later.', 'mxchat'),
                'error_code' => 'xai_rate_limit',
                'provider' => 'xai'
            ];
        }

        // Quota errors
        if (stripos($error_message, 'quota') !== false || 
            stripos($error_message, 'billing') !== false) {
            return [
                'error' => esc_html__('X.AI API quota exceeded. Please check your billing details.', 'mxchat'),
                'error_code' => 'xai_quota_exceeded',
                'provider' => 'xai'
            ];
        }

        // Server errors
        if ($status_code >= 500) {
            return [
                'error' => esc_html__('X.AI service is currently unavailable. Please try again later.', 'mxchat'),
                'error_code' => 'xai_service_unavailable',
                'provider' => 'xai'
            ];
        }

        // Generic error fallback with the actual error message
        return [
            'error' => esc_html__('X.AI API error: ', 'mxchat') . esc_html($error_message),
            'error_code' => 'xai_api_error',
            'provider' => 'xai',
            'status_code' => $status_code
        ];
    }

    $response_body = wp_remote_retrieve_body($response);
    $decoded_response = json_decode($response_body, true);

    if (isset($decoded_response['choices'][0]['message']['content'])) {
        return trim($decoded_response['choices'][0]['message']['content']);
    } else {
        //error_log('X.AI API Response Format Error: ' . print_r($decoded_response, true));
        return [
            'error' => esc_html__('Unexpected response format from X.AI.', 'mxchat'),
            'error_code' => 'xai_response_format_error',
            'provider' => 'xai'
        ];
    }
} catch (Exception $e) {
    //error_log('X.AI Exception: ' . $e->getMessage());
    return [
        'error' => esc_html__('System error when processing X.AI request: ', 'mxchat') . esc_html($e->getMessage()),
        'error_code' => 'xai_exception',
        'provider' => 'xai'
    ];
}


}
private function mxchat_generate_response_deepseek($selected_model, $deepseek_api_key, $conversation_history, $relevant_content) {
    try {
        // Ensure conversation_history is an array
        if (!is_array($conversation_history)) {
            $conversation_history = array();
        }

        // Get bot ID from session or request
        $bot_id = $this->get_current_bot_id($session_id);
        
        // Get system prompt instructions using centralized function
        $system_prompt_instructions = $this->get_system_instructions($bot_id, $session_id);
        
        // Create a new array for the formatted conversation
        $formatted_conversation = array();

        // Add system message first
        $formatted_conversation[] = array(
            'role' => 'system',
            'content' => $system_prompt_instructions . " " . $relevant_content
        );

        // Add the rest of the conversation history
        foreach ($conversation_history as $message) {
            if (is_array($message) && isset($message['role']) && isset($message['content'])) {
                $role = $message['role'];

                // Convert roles to supported format
                if ($role === 'bot' || $role === 'agent') {
                    $role = 'assistant';
                }
                if (!in_array($role, ['system', 'assistant', 'user', 'function', 'tool'])) {
                    $role = 'user';
                }

                $formatted_conversation[] = array(
                    'role' => $role,
                    'content' => $message['content']
                );
            }
        }

        $body = json_encode([
            'model' => $selected_model,
            'messages' => $formatted_conversation,
            'temperature' => 0.8,
            'stream' => false
        ]);

        $args = [
            'body'        => $body,
            'headers'     => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $deepseek_api_key,
            ],
            'timeout'     => 60,
            'redirection' => 5,
            'blocking'    => true,
            'httpversion' => '1.0',
            'sslverify'   => true,
        ];

        $response = wp_remote_post('https://api.deepseek.com/v1/chat/completions', $args);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            //error_log('DeepSeek API Error: ' . $error_message);
            return [
                'error' => esc_html__('Connection error when contacting DeepSeek: ', 'mxchat') . esc_html($error_message),
                'error_code' => 'deepseek_connection_error',
                'provider' => 'deepseek'
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $response_body = wp_remote_retrieve_body($response);
            $decoded_response = json_decode($response_body, true);

            $error_message = isset($decoded_response['error']['message']) 
                ? $decoded_response['error']['message'] 
                : 'HTTP Error ' . $status_code;
                
            $error_type = isset($decoded_response['error']['type']) 
                ? $decoded_response['error']['type'] 
                : 'unknown';
                
            //error_log('DeepSeek API HTTP Error: ' . $status_code . ' - ' . $error_message);

            // Handle specific error types
            switch ($status_code) {
                case 401:
                    return [
                        'error' => esc_html__('Authentication failed with DeepSeek. Please check your API key.', 'mxchat'),
                        'error_code' => 'deepseek_auth_error',
                        'provider' => 'deepseek'
                    ];
                
                case 400:
                    if (strpos($error_message, 'API key') !== false) {
                        return [
                            'error' => esc_html__('Invalid DeepSeek API key. Please check your API key configuration.', 'mxchat'),
                            'error_code' => 'deepseek_invalid_api_key',
                            'provider' => 'deepseek'
                        ];
                    }
                    break;
                    
                case 429:
                    if (strpos($error_message, 'quota') !== false) {
                        return [
                            'error' => esc_html__('DeepSeek API quota exceeded. Please check your billing details.', 'mxchat'),
                            'error_code' => 'deepseek_quota_exceeded',
                            'provider' => 'deepseek'
                        ];
                    } else {
                        return [
                            'error' => esc_html__('DeepSeek rate limit exceeded. Please try again later.', 'mxchat'),
                            'error_code' => 'deepseek_rate_limit',
                            'provider' => 'deepseek'
                        ];
                    }
                    
                case 500:
                case 502:
                case 503:
                case 504:
                    return [
                        'error' => esc_html__('DeepSeek service is currently unavailable. Please try again later.', 'mxchat'),
                        'error_code' => 'deepseek_service_unavailable',
                        'provider' => 'deepseek'
                    ];
            }

            // Generic error fallback
            return [
                'error' => esc_html__('DeepSeek API error: ', 'mxchat') . esc_html($error_message),
                'error_code' => 'deepseek_api_error',
                'provider' => 'deepseek',
                'status_code' => $status_code
            ];
        }

        $response_body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($response_body, true);

        if (isset($decoded_response['choices'][0]['message']['content'])) {
            return trim($decoded_response['choices'][0]['message']['content']);
        } else {
            //error_log('DeepSeek API Response Format Error: ' . print_r($decoded_response, true));
            return [
                'error' => esc_html__('Unexpected response format from DeepSeek.', 'mxchat'),
                'error_code' => 'deepseek_response_format_error',
                'provider' => 'deepseek'
            ];
        }
    } catch (Exception $e) {
        //error_log('DeepSeek Exception: ' . $e->getMessage());
        return [
            'error' => esc_html__('System error when processing DeepSeek request: ', 'mxchat') . esc_html($e->getMessage()),
            'error_code' => 'deepseek_exception',
            'provider' => 'deepseek'
        ];
    }
}
private function mxchat_generate_response_gemini($selected_model, $gemini_api_key, $conversation_history, $relevant_content) {
        // Get bot ID from session or request
        $bot_id = $this->get_current_bot_id($session_id);
        
        // Get system prompt instructions using centralized function
        $system_prompt_instructions = $this->get_system_instructions($bot_id, $session_id);
        
    // Add system prompt to relevant content
    $content_with_instructions = $system_prompt_instructions . " " . $relevant_content;
    
    // Format messages for Gemini API
    $formatted_messages = [];
    
    // Add system message as the first user message with role prefix
    // Note: Gemini doesn't have a dedicated system role, so we use a prefixed user message
    $formatted_messages[] = [
        'role' => 'user',
        'parts' => [
            ['text' => "[System Instructions] " . $content_with_instructions]
        ]
    ];
    
    // Add model response to acknowledge system instructions
    $formatted_messages[] = [
        'role' => 'model',
        'parts' => [
            ['text' => "I understand and will follow these instructions."]
        ]
    ];
    
    // Process the rest of the conversation history
    $current_role = null;
    $current_parts = [];
    
    foreach ($conversation_history as $message) {
        // Skip the first system message as we already handled it
        if ($message['role'] === 'system') {
            continue;
        }
        
        // Map roles to Gemini format
        $gemini_role = '';
        if ($message['role'] === 'user') {
            $gemini_role = 'user';
        } else if (in_array($message['role'], ['assistant', 'bot', 'agent'])) {
            $gemini_role = 'model';
        } else {
            // Skip unsupported roles
            continue;
        }
        
        // If we have a new role, add the previous message
        if ($current_role !== null && $current_role !== $gemini_role && !empty($current_parts)) {
            $formatted_messages[] = [
                'role' => $current_role,
                'parts' => $current_parts
            ];
            $current_parts = [];
        }
        
        // Set current role and add text to parts
        $current_role = $gemini_role;
        $current_parts[] = ['text' => $message['content']];
    }
    
    // Add the last message if there's content
    if ($current_role !== null && !empty($current_parts)) {
        $formatted_messages[] = [
            'role' => $current_role,
            'parts' => $current_parts
        ];
    }
    
    // Build the request body
    $body = json_encode([
        'contents' => $formatted_messages,
        'generationConfig' => [
            'temperature' => 0.7,
            'topP' => 0.95,
            'topK' => 40,
            'maxOutputTokens' => 8192,
        ],
        'safetySettings' => [
            [
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ]
        ]
    ]);
    
    // Prepare the API endpoint
    // Use v1beta for preview models (Gemini 3, experimental), v1 for stable models
    $api_version = (strpos($selected_model, 'preview') !== false || strpos($selected_model, 'exp') !== false) ? 'v1beta' : 'v1';
    $api_endpoint = 'https://generativelanguage.googleapis.com/' . $api_version . '/models/' . $selected_model . ':generateContent?key=' . $gemini_api_key;
    
    // Set up the API request
    $args = [
        'body'        => $body,
        'headers'     => [
            'Content-Type' => 'application/json',
        ],
        'timeout'     => 60,
        'redirection' => 5,
        'blocking'    => true,
        'httpversion' => '1.0',
        'sslverify'   => true,
    ];
    
    // Make the API request
    $response = wp_remote_post($api_endpoint, $args);
    
    // Process the response
    if (is_wp_error($response)) {
        return "Sorry, there was an error processing your request: " . $response->get_error_message();
    }
    
    $response_body = json_decode(wp_remote_retrieve_body($response), true);
    
    // Handle potential errors in the response
    if (isset($response_body['error'])) {
        //error_log('Gemini API Error: ' . json_encode($response_body['error']));
        return "Sorry, there was an error with the Gemini API: " . 
               (isset($response_body['error']['message']) ? $response_body['error']['message'] : 'Unknown error');
    }
    
    // Extract the response text
    if (isset($response_body['candidates'][0]['content']['parts'][0]['text'])) {
        return trim($response_body['candidates'][0]['content']['parts'][0]['text']);
    } else {
        //error_log('Unexpected Gemini API response format: ' . json_encode($response_body));
        return "Sorry, I couldn't process that request. The response format was unexpected.";
    }
}


public function test_streaming_request() {
    $options = get_option('mxchat_options', []);
    $model = $options['model'] ?? 'gpt-5.1-chat-latest';

    // Detect provider from model prefix
    $provider = strtolower(explode('-', $model)[0]);

    $sample_prompt = 'Hello! Can you stream this response back to me?';
    $messages = [['role' => 'user', 'content' => $sample_prompt]];
    $headers = [];
    $body = [];
    $url = '';
    $api_key = '';

    switch ($provider) {
        case 'gpt':
        case 'o1':
            $api_key = $options['api_key'] ?? '';
            if (empty($api_key)) return '❌ Missing API key for OpenAI';
            $url = 'https://api.openai.com/v1/chat/completions';
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key
            ];
            $body = [
                'model' => $model,
                'messages' => $messages,
                'stream' => true
            ];
            break;

        case 'claude':
            $api_key = $options['claude_api_key'] ?? '';
            if (empty($api_key)) return '❌ Missing API key for Claude';
            $url = 'https://api.anthropic.com/v1/messages';
            $headers = [
                'Content-Type: application/json',
                'x-api-key: ' . $api_key,
                'anthropic-version: 2023-06-01'
            ];
            $body = [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => 100,
                'stream' => true
            ];
            break;

        case 'grok':
            $api_key = $options['xai_api_key'] ?? '';
            if (empty($api_key)) return '❌ Missing API key for X.AI';
            $url = 'https://api.x.ai/v1/chat/completions';
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key
            ];
            $body = [
                'model' => $model,
                'messages' => $messages,
                'stream' => true
            ];
            break;

        case 'deepseek':
            if (empty($deepseek_api_key)) {
                $error_response = [
                    'error' => esc_html__('DeepSeek API key is not configured', 'mxchat'),
                    'error_code' => 'missing_deepseek_api_key'
                ];
                if ($testing_data !== null) {
                    $error_response['testing_data'] = $testing_data;
                }
                return $error_response;
            }
            if ($streaming) {
                return $this->mxchat_generate_response_deepseek_stream(
                    $selected_model,
                    $deepseek_api_key,
                    $conversation_history,
                    $relevant_content,
                    $session_id,
                    $testing_data  // Pass testing data
                );
            } else {
                $response = $this->mxchat_generate_response_deepseek(
                    $selected_model,
                    $deepseek_api_key,
                    $conversation_history,
                    $relevant_content
                );
            }
            break;

        case 'gemini':
            $api_key = $options['gemini_api_key'] ?? '';
            if (empty($api_key)) return '❌ Missing API key for Gemini';
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':streamGenerateContent?key=' . $api_key;
            $headers = ['Content-Type: application/json'];
            $body = [
                'contents' => [['role' => 'user', 'parts' => [['text' => $sample_prompt]]]],
                'generationConfig' => ['temperature' => 0.7]
            ];
            break;

        default:
            return '❌ Unsupported provider: ' . $provider;
    }

    // Do the actual streaming test
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) return "❌ cURL error: $error";
    if ($http_code !== 200) {
        $error_message = json_decode($response, true)['error']['message'] ?? 'Unknown';
        return "❌ HTTP $http_code: $error_message";
    }

    return true;
}

public function mxchat_dismiss_pre_chat_message() {
    // Get and sanitize the user identifier
    $user_id = $this->mxchat_get_user_identifier();
    $user_id = sanitize_key($user_id);

    // Set a transient to track that the user has dismissed the pre-chat message
    $transient_key = 'mxchat_pre_chat_message_dismissed_' . $user_id;
    set_transient($transient_key, true, DAY_IN_SECONDS);

    wp_send_json_success();
}

public function mxchat_check_pre_chat_message_status() {
    // Get and sanitize the user identifier
    $user_id = $this->mxchat_get_user_identifier();
    $user_id = sanitize_key($user_id);

    // Check if the transient exists (i.e., if the message was dismissed)
    $transient_key = 'mxchat_pre_chat_message_dismissed_' . $user_id;
    $dismissed = get_transient($transient_key);

    // Log the result to see if it's being set correctly
    //error_log("Check pre-chat message dismissed for $user_id: " . ($dismissed ? 'Yes' : 'No'));

    if ($dismissed) {
        wp_send_json_success(['dismissed' => true]);
    } else {
        wp_send_json_success(['dismissed' => false]);
    }

    wp_die();
}

private function mxchat_calculate_cosine_similarity($vectorA, $vectorB) {
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


public function mxchat_enqueue_scripts_styles() {
    // Fetch options from the database first to check loading strategy
    $this->options = get_option('mxchat_options');
    $loading_strategy = isset($this->options['script_loading_strategy']) ? $this->options['script_loading_strategy'] : 'default';

    // Always enqueue CSS immediately
    wp_enqueue_style(
        'mxchat-chat-css',
        plugin_dir_url(__FILE__) . '../css/chat-style.css',
        array(),
        MXCHAT_VERSION
    );

    // Handle script loading based on strategy
    if ($loading_strategy === 'default' || $loading_strategy === 'defer') {
        // Enqueue the script normally
        wp_enqueue_script(
            'mxchat-chat-js',
            plugin_dir_url(__FILE__) . '../js/chat-script.js',
            array('jquery'),
            MXCHAT_VERSION,
            true
        );

        // Add defer attribute if strategy is 'defer'
        if ($loading_strategy === 'defer') {
            wp_script_add_data('mxchat-chat-js', 'strategy', 'defer');
        }
    } else {
        // For delay or interaction-based loading, we'll use a custom loader
        // Don't enqueue the main script - we'll load it dynamically
        add_action('wp_footer', array($this, 'mxchat_output_delayed_script_loader'), 99);
    }
    $prompts_options = get_option('mxchat_prompts_options', array());

    // Check if AI theme is active - if so, skip inline colors in JavaScript
    $theme_options = get_option('mxchat_theme_options', array());
    $ai_theme_active = !empty($theme_options['active_ai_theme_css']);
    $has_bot_theme_assignments = !empty($theme_options['bot_theme_assignments']);
    $skip_inline_colors = $ai_theme_active || $has_bot_theme_assignments;

    // Prepare settings for JavaScript
    $style_settings = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mxchat_chat_nonce'),
        'model' => isset($this->options['model']) ? $this->options['model'] : 'gpt-5.1-chat-latest',
        'enable_streaming_toggle' => isset($this->options['enable_streaming_toggle']) ? $this->options['enable_streaming_toggle'] : 'on',
        'contextual_awareness_toggle' => isset($this->options['contextual_awareness_toggle']) ? $this->options['contextual_awareness_toggle'] : 'off',
        'link_target_toggle' => $this->options['link_target_toggle'] ?? 'off',
        'rate_limit_message' => $this->options['rate_limit_message'] ?? 'Rate limit exceeded. Please try again later.',
        'complianz_toggle' => isset($this->options['complianz_toggle']) && $this->options['complianz_toggle'] === 'on',
        'user_message_bg_color' => $this->options['user_message_bg_color'] ?? '#fff',
        'user_message_font_color' => $this->options['user_message_font_color'] ?? '#212121',
        'bot_message_bg_color' => $this->options['bot_message_bg_color'] ?? '#212121',
        'bot_message_font_color' => $this->options['bot_message_font_color'] ?? '#fff',
        'top_bar_bg_color' => $this->options['top_bar_bg_color'] ?? '#212121',
        'send_button_font_color' => $this->options['send_button_font_color'] ?? '#212121',
        'close_button_color' => $this->options['close_button_color'] ?? '#fff',
        'chatbot_background_color' => $this->options['chatbot_background_color'] ?? '#212121',
        'chatbot_bg_color' => $this->options['chatbot_bg_color'] ?? '#fff',
        'icon_color' => $this->options['icon_color'] ?? '#fff',
        'chat_input_font_color' => $this->options['chat_input_font_color'] ?? '#212121',
        'chat_persistence_toggle' => $this->options['chat_persistence_toggle'] ?? 'off',
        'appendWidgetToBody' => $this->options['append_to_body'] ?? 'off',
        'live_agent_message_bg_color' => $this->options['live_agent_message_bg_color'] ?? '#ffffff',
        'live_agent_message_font_color' => $this->options['live_agent_message_font_color'] ?? '#333333',
        'chat_toolbar_toggle' => $this->options['chat_toolbar_toggle'] ?? 'off',
        'mode_indicator_bg_color' => $this->options['mode_indicator_bg_color'] ?? '#767676',
        'mode_indicator_font_color' => $this->options['mode_indicator_font_color'] ?? '#ffffff',
        'toolbar_icon_color' => $this->options['toolbar_icon_color'] ?? '#212121',
        'use_pinecone' => $prompts_options['mxchat_use_pinecone'] ?? '0',
        'email_collection_enabled' => $this->options['enable_email_block'] ?? 'off', // FIXED
        'initial_email_state' => null, // Also fixed this undefined variable
        'skip_email_check' => true,
        'pinecone_enabled' => isset($prompts_options['mxchat_use_pinecone']) && $prompts_options['mxchat_use_pinecone'] === '1',
        'skip_inline_colors' => $skip_inline_colors,
        'bot_theme_assignments' => $theme_options['bot_theme_assignments'] ?? array()
    );

    // For normal/defer loading, use wp_localize_script
    // For delayed loading, we store settings in a transient to be output inline
    if ($loading_strategy === 'default' || $loading_strategy === 'defer') {
        wp_localize_script('mxchat-chat-js', 'mxchatChat', $style_settings);
    } else {
        // Store settings for the delayed loader to use
        set_transient('mxchat_delayed_settings_' . get_current_user_id(), $style_settings, 60);
    }
}

/**
 * Output the delayed script loader for performance optimization
 */
public function mxchat_output_delayed_script_loader() {
    $this->options = get_option('mxchat_options');
    $loading_strategy = isset($this->options['script_loading_strategy']) ? $this->options['script_loading_strategy'] : 'default';
    $script_url = plugin_dir_url(__FILE__) . '../js/chat-script.js?ver=' . MXCHAT_VERSION;

    // Get the stored settings
    $prompts_options = get_option('mxchat_prompts_options', array());
    $theme_options = get_option('mxchat_theme_options', array());
    $ai_theme_active = !empty($theme_options['active_ai_theme_css']);
    $has_bot_theme_assignments = !empty($theme_options['bot_theme_assignments']);
    $skip_inline_colors = $ai_theme_active || $has_bot_theme_assignments;

    $style_settings = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mxchat_chat_nonce'),
        'model' => isset($this->options['model']) ? $this->options['model'] : 'gpt-5.1-chat-latest',
        'enable_streaming_toggle' => isset($this->options['enable_streaming_toggle']) ? $this->options['enable_streaming_toggle'] : 'on',
        'contextual_awareness_toggle' => isset($this->options['contextual_awareness_toggle']) ? $this->options['contextual_awareness_toggle'] : 'off',
        'link_target_toggle' => $this->options['link_target_toggle'] ?? 'off',
        'rate_limit_message' => $this->options['rate_limit_message'] ?? 'Rate limit exceeded. Please try again later.',
        'complianz_toggle' => isset($this->options['complianz_toggle']) && $this->options['complianz_toggle'] === 'on',
        'user_message_bg_color' => $this->options['user_message_bg_color'] ?? '#fff',
        'user_message_font_color' => $this->options['user_message_font_color'] ?? '#212121',
        'bot_message_bg_color' => $this->options['bot_message_bg_color'] ?? '#212121',
        'bot_message_font_color' => $this->options['bot_message_font_color'] ?? '#fff',
        'top_bar_bg_color' => $this->options['top_bar_bg_color'] ?? '#212121',
        'send_button_font_color' => $this->options['send_button_font_color'] ?? '#212121',
        'close_button_color' => $this->options['close_button_color'] ?? '#fff',
        'chatbot_background_color' => $this->options['chatbot_background_color'] ?? '#212121',
        'chatbot_bg_color' => $this->options['chatbot_bg_color'] ?? '#fff',
        'icon_color' => $this->options['icon_color'] ?? '#fff',
        'chat_input_font_color' => $this->options['chat_input_font_color'] ?? '#212121',
        'chat_persistence_toggle' => $this->options['chat_persistence_toggle'] ?? 'off',
        'appendWidgetToBody' => $this->options['append_to_body'] ?? 'off',
        'live_agent_message_bg_color' => $this->options['live_agent_message_bg_color'] ?? '#ffffff',
        'live_agent_message_font_color' => $this->options['live_agent_message_font_color'] ?? '#333333',
        'chat_toolbar_toggle' => $this->options['chat_toolbar_toggle'] ?? 'off',
        'mode_indicator_bg_color' => $this->options['mode_indicator_bg_color'] ?? '#767676',
        'mode_indicator_font_color' => $this->options['mode_indicator_font_color'] ?? '#ffffff',
        'toolbar_icon_color' => $this->options['toolbar_icon_color'] ?? '#212121',
        'use_pinecone' => $prompts_options['mxchat_use_pinecone'] ?? '0',
        'email_collection_enabled' => $this->options['enable_email_block'] ?? 'off',
        'initial_email_state' => null,
        'skip_email_check' => true,
        'pinecone_enabled' => isset($prompts_options['mxchat_use_pinecone']) && $prompts_options['mxchat_use_pinecone'] === '1',
        'skip_inline_colors' => $skip_inline_colors,
        'bot_theme_assignments' => $theme_options['bot_theme_assignments'] ?? array()
    );

    // Determine delay time based on strategy
    $delay_ms = 0;
    switch ($loading_strategy) {
        case 'delay_1s':
            $delay_ms = 1000;
            break;
        case 'delay_3s':
            $delay_ms = 3000;
            break;
        case 'delay_5s':
            $delay_ms = 5000;
            break;
    }

    ?>
    <script type="text/javascript">
    (function() {
        var mxchatLoaded = false;
        var mxchatChat = <?php echo wp_json_encode($style_settings); ?>;
        window.mxchatChat = mxchatChat;

        function loadMxChatScript() {
            if (mxchatLoaded) return;
            mxchatLoaded = true;

            var script = document.createElement('script');
            script.src = <?php echo wp_json_encode($script_url); ?>;
            script.type = 'text/javascript';
            document.body.appendChild(script);
        }

        <?php if ($loading_strategy === 'on_interaction'): ?>
        // Load on user interaction
        var events = ['scroll', 'mousemove', 'touchstart', 'keydown', 'click'];
        events.forEach(function(evt) {
            window.addEventListener(evt, loadMxChatScript, {once: true, passive: true});
        });
        // Fallback: load after 8 seconds if no interaction
        setTimeout(loadMxChatScript, 8000);
        <?php else: ?>
        // Load after specified delay
        setTimeout(loadMxChatScript, <?php echo intval($delay_ms); ?>);
        <?php endif; ?>
    })();
    </script>
    <?php
}

/**
 * Setup the cron jobs for rate limits with guard against multiple calls
 */
public function setup_rate_limit_cron_jobs() {
    // Add a guard to prevent multiple rapid calls
    $last_setup = get_transient('mxchat_cron_setup_guard');
    if ($last_setup && (time() - $last_setup) < 60) {
        // Don't run again if we ran less than 60 seconds ago
        return;
    }
    
    // Set the guard
    set_transient('mxchat_cron_setup_guard', time(), 300); // 5 minutes
    
    try {
        // First, check if WordPress cron is disabled
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            //error_log('MxChat: WordPress cron is disabled (DISABLE_WP_CRON = true), using fallback system');
            $this->setup_fallback_rate_limit_system();
            return;
        }
        
        // Check if cron is already scheduled - if so, don't mess with it
        if (wp_next_scheduled('mxchat_reset_rate_limits')) {
            //error_log('MxChat: Rate limit cron already scheduled, skipping setup');
            return;
        }
        
        // Clear any orphaned hooks (but don't loop indefinitely)
        $hooks_to_clear = [
            'mxchat_reset_rate_limits',
            'mxchat_reset_hourly_rate_limits',
            'mxchat_reset_daily_rate_limits',
            'mxchat_reset_weekly_rate_limits',
            'mxchat_reset_monthly_rate_limits'
        ];
        
        foreach ($hooks_to_clear as $hook) {
            // Only clear a maximum of 3 instances to prevent infinite loops
            $cleared = 0;
            while (wp_next_scheduled($hook) && $cleared < 3) {
                wp_clear_scheduled_hook($hook);
                $cleared++;
            }
        }
        
        // Small delay after clearing
        usleep(100000); // 0.1 seconds
        
        // Try to schedule the event
        $initial_time = time() + 300; // Start in 5 minutes
        $result = wp_schedule_event($initial_time, 'hourly', 'mxchat_reset_rate_limits');
        
        if ($result === false) {
            //error_log('MxChat: Failed to schedule cron, using fallback system');
            $this->setup_fallback_rate_limit_system();
        } else {
            //error_log('MxChat: Successfully scheduled rate limit reset cron');
        }
        
    } catch (Exception $e) {
        //error_log('MxChat: Cron setup exception: ' . $e->getMessage());
        $this->setup_fallback_rate_limit_system();
    }
}

/**
 * Try alternative cron scheduling methods
 */
private function try_alternative_cron_scheduling($initial_time) {
    try {
        // Method 1: Try with current time instead of future time
        $result1 = wp_schedule_event(time(), 'hourly', 'mxchat_reset_rate_limits');
        if ($result1 !== false) {
            //error_log('MxChat: Alternative method 1 (current time) succeeded');
            return true;
        }
        
        // Method 2: Try with a different interval
        $result2 = wp_schedule_event($initial_time, 'daily', 'mxchat_reset_rate_limits');
        if ($result2 !== false) {
            //error_log('MxChat: Alternative method 2 (daily interval) succeeded');
            return true;
        }
        
        // Method 3: Try wp_schedule_single_event first, then recurring
        $result3 = wp_schedule_single_event($initial_time, 'mxchat_reset_rate_limits');
        if ($result3 !== false) {
            //error_log('MxChat: Alternative method 3 (single event) succeeded');
            // Schedule the next one manually in the handler
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        //error_log('MxChat: Alternative cron scheduling exception: ' . $e->getMessage());
        return false;
    }
}

/**
 * Enhanced fallback rate limit system
 */
private function setup_fallback_rate_limit_system() {
    // Set a flag to use database-based rate limit cleanup
    update_option('mxchat_use_fallback_rate_limits', true);
    
    // Schedule a one-time check to happen on the next plugin load
    update_option('mxchat_next_rate_limit_check', time() + 3600);
    
    // Also set up a more frequent fallback check (every 4 hours)
    update_option('mxchat_fallback_check_interval', 4 * 3600);
    
    //error_log('MxChat: Fallback rate limit system activated');
}

/**
 * Enhanced fallback check method
 */
public function check_fallback_rate_limits() {
    $use_fallback = get_option('mxchat_use_fallback_rate_limits', false);
    
    if (!$use_fallback) {
        return; // Regular cron is working
    }
    
    $next_check = get_option('mxchat_next_rate_limit_check', 0);
    $check_interval = get_option('mxchat_fallback_check_interval', 3600);
    
    if (time() >= $next_check) {
        //error_log('MxChat: Running fallback rate limit cleanup');
        $this->mxchat_reset_rate_limits();
        
        // Schedule next check
        update_option('mxchat_next_rate_limit_check', time() + $check_interval);
    }
}
/**
 * Enhanced rate limit check that includes fallback cleanup and bot-specific rate limits
 */
public function check_rate_limit() {
    // Check if we need to run fallback cleanup
    $use_fallback = get_option('mxchat_use_fallback_rate_limits', false);
    $next_check = get_option('mxchat_next_rate_limit_check', 0);
    
    if ($use_fallback && time() >= $next_check) {
        $this->mxchat_reset_rate_limits();
        update_option('mxchat_next_rate_limit_check', time() + 3600); // Next hour
    }
    
    // Get bot ID from current request context
    $bot_id = isset($_POST['bot_id']) ? sanitize_key($_POST['bot_id']) : 'default';
    
    // Get bot-specific options (includes rate limits if overridden)
    $bot_options = $this->get_bot_options($bot_id);
    $current_options = !empty($bot_options) ? $bot_options : $this->options;
    
    // Use bot-specific rate limits if available, otherwise fall back to default
    $rate_limits_source = isset($current_options['rate_limits']) ? $current_options['rate_limits'] : get_option('mxchat_options', [])['rate_limits'] ?? [];
    
    // Determine user role or if logged out
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        $user_id = $user->ID;
        
        // Get the user's primary role using reset() to safely get the first element
        $user_roles = $user->roles;
        
        // Safely get the first role regardless of array key structure
        if (!empty($user_roles) && is_array($user_roles)) {
            $role = reset($user_roles); // This safely gets the first element regardless of key
        } else {
            $role = 'subscriber'; // Default to subscriber if no role found
        }
    } else {
        $role = 'logged_out';
        // Use IP address for non-logged-in users
        $user_id = $this->get_client_ip();
    }
    
    // Check if rate limits are configured for this role
    if (!isset($rate_limits_source[$role])) {
        return true; // No limit set for this role
    }
    
    $limit = $rate_limits_source[$role]['limit'];
    
    // If unlimited, return true immediately
    if ($limit === 'unlimited') {
        return true;
    }
    
    // Get the option name for this user/role with safer naming (include bot_id for bot-specific limits)
    $safe_role = preg_replace('/[^a-zA-Z0-9_]/', '_', $role);
    $safe_user_id = preg_replace('/[^a-zA-Z0-9_]/', '_', $user_id);
    $safe_bot_id = preg_replace('/[^a-zA-Z0-9_]/', '_', $bot_id);
    
    // Include bot_id in option name so each bot has separate rate limits
    $option_name = 'mxchat_chat_limit_' . $safe_bot_id . '_' . $safe_role . '_' . $safe_user_id;
    
    // Get the counter data
    $limit_data = get_option($option_name, ['count' => 0, 'timestamp' => time()]);
    
    // If first request or counter reset needed, set the initial timestamp
    if ($limit_data['count'] === 0) {
        $limit_data['timestamp'] = time();
        update_option($option_name, $limit_data);
    }
    
    // Get the timeframe
    $timeframe = isset($rate_limits_source[$role]['timeframe']) ? 
        $rate_limits_source[$role]['timeframe'] : 'daily';
    
    // Check if the counter needs to be reset based on timeframe
    $current_time = time();
    $timestamp = $limit_data['timestamp'];
    $should_reset = false;
    
    switch ($timeframe) {
        case 'hourly':
            $should_reset = ($current_time - $timestamp) >= 3600; // 1 hour
            break;
        case 'daily':
            $should_reset = ($current_time - $timestamp) >= 86400; // 24 hours
            break;
        case 'weekly':
            $should_reset = ($current_time - $timestamp) >= 604800; // 7 days
            break;
        case 'monthly':
            $should_reset = ($current_time - $timestamp) >= 2592000; // 30 days
            break;
    }
    
    // Reset the counter if the timeframe has passed
    if ($should_reset) {
        $limit_data = ['count' => 0, 'timestamp' => $current_time];
        update_option($option_name, $limit_data);
    }
    
    // Check if user has exceeded their limit
    if ($limit_data['count'] >= intval($limit)) {
        // Get the custom message for this role
        $message = !empty($rate_limits_source[$role]['message']) 
            ? $rate_limits_source[$role]['message'] 
            : __('Rate limit exceeded. Please try again later.', 'mxchat');
        
        // Add timeframe information to the message if placeholders exist
        $timeframe_label = '';
        switch ($timeframe) {
            case 'hourly':
                $timeframe_label = __('hour', 'mxchat');
                break;
            case 'daily':
                $timeframe_label = __('day', 'mxchat');
                break;
            case 'weekly':
                $timeframe_label = __('week', 'mxchat');
                break;
            case 'monthly':
                $timeframe_label = __('month', 'mxchat');
                break;
        }
        
        // Replace placeholders in the message
        $message = str_replace(
            ['{limit}', '{count}', '{remaining}', '{timeframe}'],
            [intval($limit), $limit_data['count'], max(0, intval($limit) - $limit_data['count']), $timeframe_label],
            $message
        );
        
        // Process HTML links in the message
        $message = $this->process_rate_limit_message_html($message);
        
        // Return error with the processed message
        return [
            'error' => true,
            'message' => $message
        ];
    }
    
    // Increment the counter
    $limit_data['count']++;
    update_option($option_name, $limit_data);
    
    return true;
}

/**
 * Enhanced rate limit reset with better error handling
 */
public function mxchat_reset_rate_limits() {
    try {
        global $wpdb;
        $all_options = get_option('mxchat_options', []);
        $current_time = time();
        
        // Get rate limit options with a safer query and limit
        $option_names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 LIMIT 1000",
                'mxchat_chat_limit_%'
            )
        );
        
        if (empty($option_names)) {
            return;
        }
        
        $processed_count = 0;
        $max_processing_time = 30; // Maximum 30 seconds
        $start_time = time();
        
        foreach ($option_names as $option_name) {
            // Check processing time limit
            if ((time() - $start_time) > $max_processing_time) {
                //error_log('MxChat: Rate limit reset timeout after processing ' . $processed_count . ' entries');
                break;
            }
            
            // Parse the option name more safely
            if (!preg_match('/^mxchat_chat_limit_(.+)_(.+)$/', $option_name, $matches)) {
                continue;
            }
            
            $role_and_user = $matches[1] . '_' . $matches[2];
            $parts = explode('_', $role_and_user);
            
            if (count($parts) < 2) {
                continue;
            }
            
            // Extract role (everything except the last part which is user ID)
            $user_id_part = array_pop($parts);
            $role = implode('_', $parts);
            
            // Skip if role doesn't exist in our settings
            if (!isset($all_options['rate_limits'][$role])) {
                // Clean up orphaned entries
                delete_option($option_name);
                continue;
            }
            
            $timeframe = $all_options['rate_limits'][$role]['timeframe'] ?? 'daily';
            $limit_data = get_option($option_name);
            
            if (!$limit_data || !is_array($limit_data) || !isset($limit_data['timestamp'])) {
                // Clean up invalid entries
                delete_option($option_name);
                continue;
            }
            
            $timestamp = $limit_data['timestamp'];
            $should_reset = false;
            
            // Determine if we should reset based on the timeframe
            switch ($timeframe) {
                case 'hourly':
                    $should_reset = ($current_time - $timestamp) >= 3600;
                    break;
                case 'daily':
                    $should_reset = ($current_time - $timestamp) >= 86400;
                    break;
                case 'weekly':
                    $should_reset = ($current_time - $timestamp) >= 604800;
                    break;
                case 'monthly':
                    $should_reset = ($current_time - $timestamp) >= 2592000;
                    break;
            }
            
            // Reset the counter if the timeframe has passed
            if ($should_reset) {
                delete_option($option_name);
                wp_cache_delete($option_name, 'options');
                $processed_count++;
            }
        }
        
        // Clean up any orphaned cache entries
        wp_cache_delete('mxchat_all_chat_limits', 'options');
        
        //error_log("MxChat: Rate limit reset completed. Processed {$processed_count} entries.");
        
    } catch (Exception $e) {
        //error_log('MxChat: Rate limit reset error: ' . $e->getMessage());
    }
}


/**
 * Process HTML links in rate limit messages
 * 
 * @param string $message The rate limit message
 * @return string The processed message with safe HTML links
 */
private function process_rate_limit_message_html($message) {
    // Return original message if empty
    if (empty($message)) {
        return $message;
    }
    
    // First, convert markdown links to HTML
    $message = $this->convert_markdown_links($message);
    
    // Then, auto-convert any remaining plain URLs to links
    $message = $this->auto_link_urls($message);
    
    // Allow basic HTML tags for links and formatting
    $allowed_tags = [
        'a' => [
            'href' => true,
            'target' => true,
            'rel' => true,
            'title' => true,
            'class' => true
        ],
        'strong' => [],
        'em' => [],
        'br' => [],
        'b' => [],
        'i' => [],
        'span' => ['class' => true]
    ];
    
    // Sanitize but allow the specified HTML tags
    $processed_message = wp_kses($message, $allowed_tags);
    
    // If wp_kses stripped everything, return the original message as plain text
    if (empty($processed_message) && !empty($message)) {
        // Strip all HTML and return plain text as fallback
        return wp_strip_all_tags($message);
    }
    
    return $processed_message;
}

/**
 * Convert markdown links to HTML
 * 
 * @param string $text The text to process
 * @return string The text with markdown links converted to HTML
 */
private function convert_markdown_links($text) {
    // Return original text if empty
    if (empty($text)) {
        return $text;
    }
    
    // Pattern to match markdown links: [text](url)
    $pattern = '/\[([^\]]+)\]\(([^)]+)\)/';
    
    $processed_text = preg_replace_callback($pattern, function($matches) {
        $link_text = $matches[1];
        $url = $matches[2];
        
        // Clean up any trailing punctuation from the URL
        $url = rtrim($url, '.,;:!?');
        
        // Sanitize the link text and URL
        $safe_text = esc_html($link_text);
        $safe_url = esc_url($url);
        
        // Create the HTML link
        return '<a href="' . $safe_url . '" target="_blank" rel="noopener noreferrer">' . $safe_text . '</a>';
    }, $text);
    
    // If preg_replace_callback failed, return original text
    if ($processed_text === null) {
        return $text;
    }
    
    return $processed_text;
}

/**
 * Auto-convert plain URLs to clickable links
 * 
 * @param string $text The text to process
 * @return string The text with URLs converted to links
 */
private function auto_link_urls($text) {
    // Return original text if empty
    if (empty($text)) {
        return $text;
    }
    
    // Simple pattern that avoids complex lookbehinds
    // This will match URLs that are not already inside href attributes or markdown links
    $pattern = '/(?<!href=["\'])(?<!\]\()https?:\/\/[^\s<>"\')\]]+/i';
    
    $processed_text = preg_replace_callback($pattern, function($matches) {
        $url = $matches[0];
        // Clean up any trailing punctuation that might have been captured
        $url = rtrim($url, '.,;:!?');
        
        // Add target="_blank" and rel="noopener noreferrer" for security
        return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($url) . '</a>';
    }, $text);
    
    // If preg_replace_callback failed, return original text
    if ($processed_text === null) {
        return $text;
    }
    
    return $processed_text;
}


// Helper function to get client IP address
private function get_client_ip() {
    // Check for shared internet/ISP IP
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
    }
    
    // Check for IPs passing through proxies
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Use the first value in the comma-separated list
        $forwarded_for = explode(',', sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']));
        return trim($forwarded_for[0]);
    }
    
    if (!empty($_SERVER['REMOTE_ADDR'])) {
        return sanitize_text_field($_SERVER['REMOTE_ADDR']);
    }
    
    // Fallback
    return 'unknown';
}

/**
 * AJAX handler to get system information for testing panel
 */
/**
 * AJAX handler to get system information for testing panel
 */
public function mxchat_get_system_info() {
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['nonce'], 'mxchat_test_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
        return;
    }
    
    // Only allow admin users
    if (!current_user_can('administrator')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }
    
    // Get system prompt from options
    $system_prompt = isset($this->options['system_prompt_instructions']) 
        ? $this->options['system_prompt_instructions'] 
        : 'No system prompt configured';
    
    // Get selected model
    $selected_model = isset($this->options['model']) ? $this->options['model'] : 'gpt-5.1-chat-latest';
    
    // Check if OpenRouter is being used
    $is_openrouter = ($selected_model === 'openrouter');
    $openrouter_model = '';
    
    if ($is_openrouter) {
        // Get the actual OpenRouter model that's selected
        $openrouter_model = isset($this->options['openrouter_selected_model']) 
            ? $this->options['openrouter_selected_model'] 
            : 'No OpenRouter model selected';
        
        // Update selected_model display to show both
        $selected_model = 'OpenRouter: ' . $openrouter_model;
    }
    
    // Get API key status (just check if they exist, don't expose the keys)
    $api_status = [];
    $api_status['openai'] = !empty($this->options['api_key']);
    $api_status['claude'] = !empty($this->options['claude_api_key']);
    $api_status['gemini'] = !empty($this->options['gemini_api_key']);
    $api_status['xai'] = !empty($this->options['xai_api_key']);
    $api_status['deepseek'] = !empty($this->options['deepseek_api_key']);
    $api_status['openrouter'] = !empty($this->options['openrouter_api_key']);
    
    wp_send_json_success([
        'system_prompt' => $system_prompt,
        'selected_model' => $selected_model,
        'is_openrouter' => $is_openrouter,
        'openrouter_model' => $openrouter_model,
        'api_status' => $api_status
    ]);
}

/**
 * AJAX handler to get similarity threshold
 */
public function mxchat_get_similarity_threshold() {
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['nonce'], 'mxchat_test_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
        return;
    }
    
    // Only allow admin users
    if (!current_user_can('administrator')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }
    
    // Get similarity threshold from main options (default 35%)
    $similarity_threshold = isset($this->options['similarity_threshold']) 
        ? ((int) $this->options['similarity_threshold']) / 100 
        : 0.35;
    
    wp_send_json_success([
        'threshold' => $similarity_threshold,
        'threshold_percentage' => ($similarity_threshold * 100) . '%'
    ]);
}

/**
 * AJAX handler to get knowledge base status
 */
public function mxchat_get_kb_status() {
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['nonce'], 'mxchat_test_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
        return;
    }

    // Only allow admin users
    if (!current_user_can('administrator')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }

    // Check OpenAI Vector Store first (takes priority)
    $vectorstore_options = get_option('mxchat_openai_vectorstore_options', array());
    $use_vectorstore = (isset($vectorstore_options['mxchat_use_openai_vectorstore']) && $vectorstore_options['mxchat_use_openai_vectorstore'] === '1');

    if ($use_vectorstore) {
        $vectorstore_ids = $vectorstore_options['mxchat_openai_vectorstore_ids'] ?? '';
        $id_count = !empty($vectorstore_ids) ? count(array_filter(array_map('trim', explode(',', $vectorstore_ids)))) : 0;

        $kb_info = [
            'type' => 'OpenAI Vector Store',
            'status' => 'Active',
            'documents' => $id_count > 0 ? $id_count . ' vector store' . ($id_count > 1 ? 's' : '') . ' configured' : 'No vector stores configured'
        ];

        wp_send_json_success($kb_info);
        return;
    }

    // Check Pinecone vs WordPress
    $addon_options = get_option('mxchat_pinecone_addon_options', array());
    $use_pinecone = (isset($addon_options['mxchat_use_pinecone']) && $addon_options['mxchat_use_pinecone'] === '1');

    $kb_info = [
        'type' => $use_pinecone ? 'Pinecone' : 'WordPress Database',
        'status' => 'Active'
    ];

    // Get document count
    if ($use_pinecone) {
        $kb_info['documents'] = 'Connected to Pinecone';
        $kb_info['api_configured'] = !empty($addon_options['mxchat_pinecone_api_key']);
    } else {
        // Count documents in WordPress database
        global $wpdb;
        $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $kb_info['documents'] = $count ? $count . ' documents' : 'No documents';
    }

    wp_send_json_success($kb_info);
}

/**
 * AJAX handler to start a completely fresh session (NEW - replaces old clear session)
 */
public function mxchat_start_fresh_session() {
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['nonce'], 'mxchat_test_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
        return;
    }
    
    // Only allow admin users
    if (!current_user_can('administrator')) {
        wp_send_json_error(['message' => 'Unauthorized']);
        return;
    }
    
    $old_session_id = isset($_POST['old_session_id']) ? sanitize_text_field($_POST['old_session_id']) : '';
    $new_session_id = isset($_POST['new_session_id']) ? sanitize_text_field($_POST['new_session_id']) : '';
    
    if (empty($old_session_id)) {
        wp_send_json_error(['message' => 'Old session ID required']);
        return;
    }
    
    // If no new session ID provided, generate one
    if (empty($new_session_id)) {
        $new_session_id = 'mxchat_chat_' . substr(md5(uniqid()), 0, 9);
    }
    
    // Clear ALL data associated with the old session
    $this->clear_complete_session_data($old_session_id);
    
    // Initialize the new session
    $this->initialize_fresh_session($new_session_id);
    
    wp_send_json_success([
        'message' => 'Fresh session started successfully',
        'new_session_id' => $new_session_id,
        'old_session_id' => $old_session_id
    ]);
}

/**
 * Clear ALL data associated with a session (ENHANCED)
 */
private function clear_complete_session_data($session_id) {
    // Clear chat history
    delete_option("mxchat_history_{$session_id}");
    
    // Clear chat mode
    delete_option("mxchat_mode_{$session_id}");
    
    // Clear any PDF/Word transients
    $this->clear_pdf_transients($session_id);
    if (method_exists($this, 'clear_word_transients')) {
        $this->clear_word_transients($session_id);
    }
    
    // Clear agent-related data
    delete_option("mxchat_channel_{$session_id}");
    delete_option("mxchat_agent_name_{$session_id}");
    delete_option("mxchat_email_{$session_id}");
    
    // Clear any recommendation flow state
    delete_option("mxchat_sr_flow_state_{$session_id}");
    
    // Clear any cached embeddings or context
    delete_transient("mxchat_context_{$session_id}");
    delete_transient("mxchat_last_query_{$session_id}");
    
    // Clear any testing data
    delete_transient("mxchat_testing_data_{$session_id}");
    
    // Clear any rate limiting data for this session
    delete_transient("mxchat_rate_limit_{$session_id}");
    
    // Clear any other session-specific transients
    delete_transient("mxchat_waiting_for_pdf_url_{$session_id}");
    delete_transient("mxchat_include_pdf_in_context_{$session_id}");
    delete_transient("mxchat_include_word_in_context_{$session_id}");

    // Clear form addon state (pending forms and submitted forms)
    delete_option("mxchat_pending_form_{$session_id}");
    delete_option("mxchat_submitted_forms_{$session_id}");

    //error_log("MxChat: Cleared all data for session: {$session_id}");
}

/**
 * Initialize a fresh session with default data
 */
private function initialize_fresh_session($session_id) {
    // Set default chat mode
    update_option("mxchat_mode_{$session_id}", 'ai');
    
    //error_log("MxChat: Initialized fresh session: {$session_id}");
}

/**
 * Helper method to clear Word document transients (if you have Word support)
 */
private function clear_word_transients($session_id) {
    delete_transient('mxchat_word_url_' . $session_id);
    delete_transient('mxchat_word_filename_' . $session_id);
    delete_transient('mxchat_word_embeddings_' . $session_id);
    delete_transient('mxchat_include_word_in_context_' . $session_id);
}

/**
 * Simplified testing data capture method (CLEANED UP)
 */
private function capture_testing_data($user_embedding, $message, $session_id) {
    // Only capture for admin users
    if (!current_user_can('administrator')) {
        return null;
    }
    
    $testing_data = [
        'query' => $message,
        'timestamp' => time(),
        'top_matches' => [],
        'action_matches' => [] //   Add action matches
    ];
    
    // Get similarity threshold
    $similarity_threshold = isset($this->options['similarity_threshold']) 
        ? ((int) $this->options['similarity_threshold']) / 100 
        : 0.35;
    
    $testing_data['similarity_threshold'] = $similarity_threshold;
    
    // Use the real similarity analysis if available
    if ($this->last_similarity_analysis !== null) {
        $testing_data['knowledge_base_type'] = $this->last_similarity_analysis['knowledge_base_type'];
        $testing_data['top_matches'] = $this->last_similarity_analysis['top_matches'];
        $testing_data['total_documents_checked'] = $this->last_similarity_analysis['total_checked'] ?? 0;
    } else {
        // Fallback: determine knowledge base type
        $addon_options = get_option('mxchat_pinecone_addon_options', array());
        $use_pinecone = (isset($addon_options['mxchat_use_pinecone']) && $addon_options['mxchat_use_pinecone'] === '1');
        
        $testing_data['knowledge_base_type'] = $use_pinecone ? 'Pinecone' : 'WordPress Database';
    }
    
    //   Include action analysis if available
    if (isset($this->last_action_analysis) && !empty($this->last_action_analysis)) {
        $testing_data['action_matches'] = $this->last_action_analysis;
        
        // Clear it after capturing to avoid stale data
        $this->last_action_analysis = null;
    }
    
    return $testing_data;
}


/**
 *   Track URL clicks from chatbot responses
 */
public function mxchat_track_url_click() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mxchat_chat_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
        wp_die();
    }
    
    $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
    $clicked_url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
    $message_context = isset($_POST['message_context']) ? sanitize_textarea_field($_POST['message_context']) : '';
    
    if (empty($session_id) || empty($clicked_url)) {
        wp_send_json_error(['message' => 'Missing required data']);
        wp_die();
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_url_clicks';
    
    // Insert click tracking record
    $wpdb->insert(
        $table_name,
        [
            'session_id' => $session_id,
            'clicked_url' => $clicked_url,
            'message_context' => $message_context,
            'click_timestamp' => current_time('mysql', 1),
            'user_ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT']
        ]
    );
    
    wp_send_json_success(['message' => 'Click tracked']);
    wp_die();
}

/**
 *   Get URL click analytics for a session
 */
public function mxchat_get_url_clicks($session_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_url_clicks';
    
    $clicks = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE session_id = %s ORDER BY click_timestamp ASC",
        $session_id
    ));
    
    return $clicks;
}
/**
 *   Track the originating page where chat was started
 */
public function mxchat_track_originating_page() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mxchat_chat_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
        wp_die();
    }
    
    $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
    $page_url = isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : '';
    $page_title = isset($_POST['page_title']) ? sanitize_text_field($_POST['page_title']) : '';
    
    if (empty($session_id)) {
        wp_send_json_error(['message' => 'Missing session ID']);
        wp_die();
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_chat_transcripts';
    
    // Check if we've already tracked for this session
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name 
         WHERE session_id = %s 
         AND originating_page_url IS NOT NULL",
        $session_id
    ));
    
    if ($existing > 0) {
        wp_send_json_success(['message' => 'Already tracked']);
        wp_die();
    }
    
    // Update the first message in this session with originating page info
    $wpdb->query($wpdb->prepare(
        "UPDATE $table_name 
         SET originating_page_url = %s, 
             originating_page_title = %s 
         WHERE session_id = %s 
         ORDER BY timestamp ASC 
         LIMIT 1",
        $page_url,
        $page_title,
        $session_id
    ));
    
    wp_send_json_success(['message' => 'Originating page tracked']);
    wp_die();
}

/**
 * Validate and clean URLs from AI response
 * Removes any URLs that aren't in the knowledge base
 * 
 * @param string $response_text The AI-generated response
 * @param array $valid_urls Array of URLs from the knowledge base
 * @return string Cleaned response with invalid URLs removed/flagged
 */
private function validate_and_clean_urls($response_text, $valid_urls) {
    // DEBUG: Log what we're working with
    error_log("=== MxChat URL Validation Debug ===");
    error_log("Valid URLs count: " . count($valid_urls));
    error_log("Valid URLs: " . print_r($valid_urls, true));
    error_log("Response text length: " . strlen($response_text));
    error_log("Response text preview: " . substr($response_text, 0, 500));
    
    // If no valid URLs provided or empty response, return as-is
    if (empty($valid_urls) || empty($response_text)) {
        error_log("Validation skipped - empty valid_urls or response");
        return $response_text;
    }
    
    // Extract all URLs from the AI response
    // This regex matches http:// and https:// URLs
    preg_match_all(
        '#\bhttps?://[^\s<>"\')\]]+#i', 
        $response_text, 
        $matches
    );
    
    // If no URLs found in response, return as-is
    if (empty($matches[0])) {
        error_log("No URLs found in response");
        return $response_text;
    }
    
    $found_urls = $matches[0];
    $cleaned_response = $response_text;
    $removed_count = 0;
    
    // Normalize valid URLs for comparison (remove trailing slashes, fragments, etc.)
    $normalized_valid_urls = array_map(function($url) {
        // Remove trailing slash
        $url = rtrim($url, '/');
        // Remove URL fragments (#section)
        $url = preg_replace('/#.*$/', '', $url);
        // Remove trailing punctuation that might have been captured
        $url = rtrim($url, '.,;:!?');
        return $url;
    }, $valid_urls);
    
    error_log("Normalized valid URLs: " . print_r($normalized_valid_urls, true));
    
    foreach ($found_urls as $found_url) {
        // Clean up the found URL (remove trailing punctuation that might have been captured)
        $clean_found_url = rtrim($found_url, '.,;:!?)');
        
        // DEBUG: Log each URL being checked
        error_log("Checking found URL: " . $found_url);
        
        // Normalize for comparison
        $normalized_found = rtrim($clean_found_url, '/');
        $normalized_found = preg_replace('/#.*$/', '', $normalized_found);
        
        error_log("Normalized found URL: " . $normalized_found);
        
        // Check if this URL exists in our valid URLs list
        $is_valid = false;
        
        error_log("Starting validation checks for: " . $normalized_found);
        
        // First, try exact match
        if (in_array($normalized_found, $normalized_valid_urls)) {
            $is_valid = true;
            error_log("EXACT MATCH FOUND");
        } else {
            error_log("No exact match, checking variations...");
            // If no exact match, check if it's a variation (with query params, etc.)
            foreach ($normalized_valid_urls as $valid_url) {
                error_log("  Comparing against valid URL: " . $valid_url);
                
                // Check if the found URL starts with a valid URL (handles query params)
                if (strpos($normalized_found, $valid_url) === 0) {
                    // Check what comes after the valid URL
                    $remainder = substr($normalized_found, strlen($valid_url));
                    
                    // Only valid if:
                    // 1. Exact match (remainder is empty)
                    // 2. Query params (starts with ?)
                    // 3. Fragment (starts with #)
                    if (empty($remainder) || $remainder[0] === '?' || $remainder[0] === '#') {
                        $is_valid = true;
                        error_log("  MATCH: Found URL is valid variation of base URL");
                        break;
                    } else {
                        error_log("  NOT A MATCH: Found URL extends path beyond valid URL (remainder: " . $remainder . ")");
                    }
                }
                // Also check the reverse (in case valid URL has query params)
                if (strpos($valid_url, $normalized_found) === 0) {
                    $is_valid = true;
                    error_log("  MATCH: Valid URL starts with found URL");
                    break;
                }
            }
            
            if (!$is_valid) {
                error_log("NO MATCH FOUND - URL should be removed");
            }
        }
        
        // If URL is not valid, remove it from the response
        if (!$is_valid) {
            // Log the removal for debugging
            error_log("MxChat: Removed hallucinated URL: " . $found_url);
            error_log("MxChat: Valid URLs were: " . implode(', ', array_slice($normalized_valid_urls, 0, 5)));
            
            $removed_count++;
            
            // Check if URL is part of a markdown link: [text](url)
            $markdown_pattern = '/\[([^\]]+)\]\(' . preg_quote($found_url, '/') . '\)/';
            if (preg_match($markdown_pattern, $cleaned_response)) {
                error_log("Found markdown link, removing but keeping text");
                // Remove the markdown link but keep the text
                $cleaned_response = preg_replace($markdown_pattern, '$1', $cleaned_response);
            } 
            // Check if URL is part of an HTML link: <a href="url">text</a>
            else if (preg_match('/<a[^>]*href=["\']' . preg_quote($found_url, '/') . '["\'][^>]*>(.*?)<\/a>/i', $cleaned_response, $link_match)) {
                error_log("Found HTML link, removing but keeping text");
                // Remove the HTML link but keep the text
                $link_text = $link_match[1];
                $cleaned_response = preg_replace(
                    '/<a[^>]*href=["\']' . preg_quote($found_url, '/') . '["\'][^>]*>.*?<\/a>/i',
                    $link_text,
                    $cleaned_response
                );
            }
            // Otherwise just remove the bare URL
            else {
                error_log("Removing bare URL");
                $cleaned_response = str_replace($found_url, '', $cleaned_response);
            }
        }
    }
    
    // Log summary if any URLs were removed
    if ($removed_count > 0) {
        error_log("MxChat: URL Validation Summary - Removed {$removed_count} hallucinated URL(s)");
    } else {
        error_log("MxChat: URL Validation Summary - No URLs removed, all were valid");
    }
    
    // Clean up any double spaces or awkward punctuation left behind
    // IMPORTANT: Only collapse horizontal whitespace (spaces/tabs), preserve newlines for markdown formatting
    $cleaned_response = preg_replace('/[^\S\n]+/', ' ', $cleaned_response);  // Collapse spaces/tabs but NOT newlines
    $cleaned_response = preg_replace('/[^\S\n]+([.,;:!?])/', '$1', $cleaned_response);  // Same for punctuation cleanup
    
    error_log("Final cleaned response: " . $cleaned_response);
    
    return trim($cleaned_response);
}

/**
 * AJAX handler to get current chat mode for a session
 */
public function mxchat_get_current_chat_mode() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mxchat_chat_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
        wp_die();
    }
    
    $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
    
    if (empty($session_id)) {
        wp_send_json_error(['message' => 'Session ID missing']);
        wp_die();
    }
    
    // Get the current chat mode for this session
    $chat_mode = get_option("mxchat_mode_{$session_id}", 'ai');
    
    wp_send_json_success([
        'chat_mode' => $chat_mode
    ]);
    wp_die();
}



}
?>
