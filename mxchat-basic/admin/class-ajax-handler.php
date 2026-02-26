<?php
/**
 * File: admin/class-ajax-handler.php
 *
 * Handles all AJAX requests for MxChat admin functionality
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MxChat_Ajax_Handler {
    
    private $pinecone_manager = null;
        
    /**
     * Constructor - Register all AJAX hooks
     */
    public function __construct() {
        $this->mxchat_init_ajax_hooks();
    }
    

    /**
     * Register all AJAX action hooks
     */
private function mxchat_init_ajax_hooks() {
    // Settings AJAX
    add_action('wp_ajax_mxchat_save_setting', array($this, 'mxchat_save_setting_callback'));
    add_action('wp_ajax_mxchat_save_prompts_setting', array($this, 'mxchat_save_prompts_setting_callback'));
    add_action('wp_ajax_migrate_pinecone_settings', array($this, 'ajax_migrate_pinecone_settings'));
    
    // License AJAX 
    add_action('wp_ajax_mxchat_handle_activate_license', array($this, 'mxchat_handle_activate_license'));
    add_action('wp_ajax_mxchat_check_license_status', array($this, 'mxchat_check_license_status'));
    add_action('wp_ajax_mxchat_deactivate_license', array($this, 'mxchat_deactivate_license'));
    
    // Actions & Intents AJAX
    add_action('wp_ajax_mxchat_toggle_action', array($this, 'mxchat_toggle_action'));
    add_action('wp_ajax_mxchat_update_intent_threshold', array($this, 'mxchat_update_intent_threshold'));

    add_action('wp_ajax_mxchat_save_selected_bot', array($this, 'mxchat_save_selected_bot'));
    add_action('wp_ajax_mxchat_check_api_keys', array($this, 'mxchat_check_api_keys'));

    // Debug & Optimization AJAX
    add_action('wp_ajax_mxchat_toggle_debug_mode', array($this, 'mxchat_toggle_debug_mode_callback'));
    add_action('wp_ajax_mxchat_get_debug_log', array($this, 'mxchat_get_debug_log_callback'));
    add_action('wp_ajax_mxchat_clear_debug_log', array($this, 'mxchat_clear_debug_log_callback'));
    add_action('wp_ajax_mxchat_export_settings', array($this, 'mxchat_export_settings_callback'));
    add_action('wp_ajax_mxchat_reset_all_settings', array($this, 'mxchat_reset_all_settings_callback'));
}

    // ========================================
    // SETTINGS AJAX HANDLERS
    // ========================================

/**
 * Validates and saves chat settings via AJAX request
 */
public function mxchat_save_setting_callback() {
    check_ajax_referer('mxchat_save_setting_nonce');
    if (!current_user_can('manage_options')) {
        ('MXChat Save: Unauthorized access attempt');
        wp_send_json_error(['message' => esc_html__('Unauthorized', 'mxchat')]);
    }

    $name = isset($_POST['name']) ? $_POST['name'] : '';
    // Strip slashes from the value before saving
    $value = isset($_POST['value']) ? stripslashes($_POST['value']) : '';

    //error_log('MXChat Save: Processing field name: ' . $name);
    //error_log('MXChat Save: Field value: ' . $value);

    if (empty($name)) {
        //error_log('MXChat Save: Empty field name detected');
        wp_send_json_error(['message' => esc_html__('Invalid field name', 'mxchat')]);
    }

    // Load the full options array
    $options = get_option('mxchat_options', []);
    //error_log('MXChat Save: Current options array: ' . print_r($options, true));

    // Extract field name from mxchat_options[field_name] format if present
    // But preserve the full name for special cases like rate_limits that need the full path
    $field_name = $name;
    if (preg_match('/^mxchat_options\[([^\[\]]+)\]$/', $name, $matches)) {
        $field_name = $matches[1];
    }

    // Handle special cases
    switch ($field_name) {
        case 'model':
            //error_log('MXChat Save: Processing model selection');
            //error_log('MXChat Save: Model value received: ' . $value);
            //error_log('MXChat Save: Value type: ' . gettype($value));
            //error_log('MXChat Save: Value length: ' . strlen($value));
            //error_log('MXChat Save: Value === "openrouter": ' . ($value === 'openrouter' ? 'YES' : 'NO'));
            
            // Allow 'openrouter' or validate against whitelist
            if ($value === 'openrouter') {
                //error_log('MXChat Save: Setting model to openrouter');
                $options['model'] = 'openrouter';
            } else {
                //error_log('MXChat Save: Checking against whitelist');
                 $allowed_models = array(
                            'gemini-3-pro-preview', 'gemini-3-flash-preview', 'gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.5-flash-lite',
                            'gemini-2.0-flash', 'gemini-2.0-flash-lite', 'gemini-1.5-pro', 'gemini-1.5-flash',
                            'grok-4-0709', 'grok-4-1-fast-reasoning', 'grok-4-1-fast-non-reasoning', 'grok-3-beta', 'grok-3-fast-beta', 'grok-3-mini-beta',
                            'grok-3-mini-fast-beta', 'grok-2',
                            'deepseek-chat',
                            'claude-opus-4-6', 'claude-opus-4-5', 'claude-sonnet-4-6',
                            'claude-sonnet-4-5-20250929', 'claude-opus-4-1-20250805', 'claude-haiku-4-5-20251001',
                            'claude-opus-4-20250514', 'claude-sonnet-4-20250514',
                            'gpt-5.2', 'gpt-5.1-chat-latest', 'gpt-5.1-2025-11-13', 'gpt-5', 'gpt-5-mini', 'gpt-5-nano',
                        );
                
                //error_log('MXChat Save: in_array result: ' . (in_array($value, $allowed_models) ? 'YES' : 'NO'));
                
                if (in_array($value, $allowed_models)) {
                    //error_log('MXChat Save: Model is in whitelist, saving');
                    $options['model'] = sanitize_text_field($value);
                } else {
                    //error_log('MXChat Save: Invalid model rejected: ' . $value);
                    //error_log('MXChat Save: Allowed models: ' . print_r($allowed_models, true));
                    wp_send_json_error(['message' => esc_html__('Invalid model selected', 'mxchat')]);
                    return;
                }
            }
            break;
            
        case 'openrouter_selected_model':
            //error_log('MXChat Save: Processing OpenRouter model: ' . $value);
            $options['openrouter_selected_model'] = sanitize_text_field($value);
            // Force immediate save for new keys
            //error_log('MXChat Save: OpenRouter model saved immediately');
            break;
        
        case 'openrouter_selected_model_name':
            //error_log('MXChat Save: Processing OpenRouter model name: ' . $value);
            $options['openrouter_selected_model_name'] = sanitize_text_field($value);
            // Force immediate save for new keys
            //error_log('MXChat Save: OpenRouter model name saved immediately');
            break;
            
        case 'openrouter_api_key':
            //error_log('MXChat Save: Processing OpenRouter API key');
            $options['openrouter_api_key'] = sanitize_text_field($value);
            break;
            
        // REMOVED DUPLICATE case 'openrouter_selected_model_name' HERE!
            
        case 'additional_popular_questions':
            //error_log('MXChat Save: Processing additional_popular_questions');
            $questions = json_decode($value, true); // No need for stripslashes here
            if (is_array($questions)) {
                $options[$field_name] = $questions;
                // Also update old option for backwards compatibility
                update_option('additional_popular_questions', $questions);
                //error_log('MXChat Save: Saved ' . count($questions) . ' additional questions');
            } else {
                //error_log('MXChat Save: Failed to decode questions JSON');
            }
            break;
        case 'email_blocker_header_content':
            //error_log('MXChat Save: Processing email_blocker_header_content');
            // Allow HTML content but sanitize it safely
            $options[$field_name] = wp_kses_post($value);
            break;
        case 'email_blocker_button_text':
            //error_log('MXChat Save: Processing email_blocker_button_text');
            $options[$field_name] = sanitize_text_field($value);
            break;
        case 'name_field_placeholder':
            //error_log('MXChat Save: Processing name_field_placeholder');
            $options[$field_name] = sanitize_text_field($value);
            break;
        case 'similarity_threshold':
            //error_log('MXChat Save: Processing similarity_threshold');
            // Validate and save - enforce min 20, max 85
            $threshold = intval($value);
            if ($threshold < 20) $threshold = 20;
            if ($threshold > 85) $threshold = 85;
            $options[$field_name] = $threshold;
            break;
        case 'rag_sources_limit':
            //error_log('MXChat Save: Processing rag_sources_limit');
            // Validate and save - enforce min 3, max 10, default 6
            $rag_limit = intval($value);
            if ($rag_limit < 3) $rag_limit = 3;
            if ($rag_limit > 10) $rag_limit = 10;
            $options[$field_name] = $rag_limit;
            break;
        case 'user_message_bg_color':
        case 'user_message_font_color':
        case 'bot_message_bg_color':
        case 'bot_message_font_color':
        case 'top_bar_bg_color':
        case 'send_button_font_color':
        case 'chatbot_background_color':
        case 'icon_color':
        case 'chat_input_font_color':
        case 'live_agent_message_bg_color':
        case 'live_agent_message_font_color':
        case 'mode_indicator_bg_color':
        case 'mode_indicator_font_color':
        case 'toolbar_icon_color':
        case 'quick_questions_toggle_color':
            //error_log('MXChat Save: Processing color value: ' . $field_name);
            // Store color values directly
            $options[$field_name] = $value;
            break;
        case 'live_agent_status':
            //error_log('MXChat Save: Processing live_agent_status');
            // Set the new value
            $options[$field_name] = ($value === 'on') ? 'on' : 'off';
            break;
        case 'enable_web_search':
            //error_log('MXChat Save: Processing enable_web_search');
            $options[$field_name] = ($value === 'on') ? 'on' : 'off';
            break;
        case 'enable_woocommerce_integration':
            //error_log('MXChat Save: Processing enable_woocommerce_integration');
            // Handle values that used to be 1/0
            $options[$field_name] = ($value === 'on' || $value === '1') ? 'on' : 'off';
            break;
        case 'post_type_visibility_mode':
            // Validate mode value
            $allowed_modes = array('all', 'include', 'exclude');
            $options[$field_name] = in_array($value, $allowed_modes) ? $value : 'all';
            break;
        case 'post_type_visibility_list':
            // Handle JSON array of post types
            $post_types = json_decode($value, true);
            if (is_array($post_types)) {
                // Sanitize each post type slug
                $options[$field_name] = array_map('sanitize_key', $post_types);
            } else {
                $options[$field_name] = array();
            }
            break;
        case 'script_loading_strategy':
            // Validate script loading strategy value
            $allowed_strategies = array('default', 'defer', 'delay_1s', 'delay_3s', 'delay_5s', 'on_interaction');
            $options[$field_name] = in_array($value, $allowed_strategies) ? $value : 'default';
            break;
        default:
            // Handle transcripts options
            if (strpos($name, 'mxchat_transcripts_options') !== false) {
                // Extract field name from mxchat_transcripts_options[field_name]
                if (preg_match('/mxchat_transcripts_options\[([^\]]+)\]/', $name, $matches)) {
                    $field_name = $matches[1];

                    // Get current transcripts options
                    $transcripts_options = get_option('mxchat_transcripts_options', array());

                    // Ensure it's an array
                    if (!is_array($transcripts_options)) {
                        $transcripts_options = array();
                    }

                    // Handle checkbox values (convert 'on'/'off' to 1/0)
                    if ($value === 'on' || $value === '1') {
                        $transcripts_options[$field_name] = 1;
                    } else if ($value === 'off' || $value === '0' || $value === '') {
                        $transcripts_options[$field_name] = 0;
                    } else {
                        // For text/select fields, sanitize appropriately
                        if ($field_name === 'mxchat_notification_email') {
                            $transcripts_options[$field_name] = sanitize_email($value);
                        } else {
                            $transcripts_options[$field_name] = sanitize_text_field($value);
                        }
                    }

                    // Use direct database update to bypass any filters
                    global $wpdb;

                    // Serialize the options array
                    $serialized = maybe_serialize($transcripts_options);

                    // Check if the option already exists in the database
                    $existing = $wpdb->get_var("SELECT option_id FROM {$wpdb->options} WHERE option_name = 'mxchat_transcripts_options'");

                    if ($existing) {
                        // Option exists, do an update
                        $result = $wpdb->update(
                            $wpdb->options,
                            array('option_value' => $serialized),
                            array('option_name' => 'mxchat_transcripts_options'),
                            array('%s'),
                            array('%s')
                        );
                    } else {
                        // Option doesn't exist (new install), do an insert
                        $result = $wpdb->insert(
                            $wpdb->options,
                            array(
                                'option_name' => 'mxchat_transcripts_options',
                                'option_value' => $serialized,
                                'autoload' => 'yes'
                            ),
                            array('%s', '%s', '%s')
                        );
                    }

                    // Clear all caches after direct DB update
                    wp_cache_delete('mxchat_transcripts_options', 'options');
                    wp_cache_delete('alloptions', 'options');
                    wp_cache_flush();

                    wp_send_json_success(['message' => esc_html__('Setting saved', 'mxchat')]);
                    return;
                }
            }
            // First check for rate limits settings
            else if (strpos($name, 'mxchat_options[rate_limits]') !== false) {
                //error_log('MXChat Save: Detected rate_limits field: ' . $name);

                // Extract role ID and setting from the name
                preg_match('/\[rate_limits\]\[(.*?)\]\[(.*?)\]/', $name, $matches);
                //error_log('MXChat Save: Regex matches: ' . print_r($matches, true));

                if (isset($matches[1]) && isset($matches[2])) {
                    $role_id = $matches[1];
                    $setting_key = $matches[2]; // limit, timeframe, or message

                    //error_log('MXChat Save: Role ID = ' . $role_id . ', Setting Key = ' . $setting_key);

                    // Initialize rate_limits if it doesn't exist
                    if (!isset($options['rate_limits'])) {
                       // //error_log('MXChat Save: Initializing rate_limits array');
                        $options['rate_limits'] = [];
                    }

                    // Initialize role settings if it doesn't exist
                    if (!isset($options['rate_limits'][$role_id])) {
                        //error_log('MXChat Save: Initializing rate_limits for role: ' . $role_id);
                        $options['rate_limits'][$role_id] = [
                            'limit' => ($role_id === 'logged_out') ? '10' : '100',
                            'timeframe' => 'daily',
                            'message' => 'Rate limit exceeded. Please try again later.'
                        ];
                    }

                    // Update the specific setting
                    $options['rate_limits'][$role_id][$setting_key] = $value;
                    //error_log('MXChat Save: Updated rate_limits[' . $role_id . '][' . $setting_key . '] = ' . $value);
                } else {
                    //error_log('MXChat Save: Failed to parse rate_limits pattern: ' . $name);
                }
            }
            // Then check for role rate limits (old format)
            else if (strpos($name, 'mxchat_options[role_rate_limits]') !== false) {
                //error_log('MXChat Save: Processing role_rate_limits field: ' . $name);
                // Extract role ID from the name
                preg_match('/\[role_rate_limits\]\[(.*?)\]/', $name, $matches);
                //error_log('MXChat Save: Regex matches: ' . print_r($matches, true));

                if (isset($matches[1])) {
                    $role_id = $matches[1];
                    // Initialize role_rate_limits if it doesn't exist
                    if (!isset($options['role_rate_limits'])) {
                        //error_log('MXChat Save: Initializing role_rate_limits array');
                        $options['role_rate_limits'] = [];
                    }
                    // Update the specific role's rate limit
                    $options['role_rate_limits'][$role_id] = sanitize_text_field($value);
                    //error_log('MXChat Save: Updated role_rate_limits[' . $role_id . '] = ' . $value);
                } else {
                    //error_log('MXChat Save: Failed to parse role_rate_limits pattern: ' . $name);
                }
            }
            // Handle toggles - check both extracted field_name and original name for toggle detection
            else if (strpos($field_name, 'toggle') !== false || in_array($field_name, [
                'chat_persistence_toggle',
                'privacy_toggle',
                'complianz_toggle',
                'chat_toolbar_toggle',
                'show_pdf_upload_button',
                'show_word_upload_button',
                'enable_streaming_toggle',
                'contextual_awareness_toggle',
                'citation_links_toggle',
                'enable_email_block',
                'enable_name_field',
                'show_frontend_debugger'
            ])) {
                //error_log('MXChat Save: Processing toggle: ' . $field_name);
                $options[$field_name] = ($value === 'on') ? 'on' : 'off';
            } else {
                //error_log('MXChat Save: Processing standard field: ' . $field_name);
                // Store all other values directly using the extracted field name
                $options[$field_name] = $value;
            }
            break;
    }

    // Save all updates to the options array
    $updated = update_option('mxchat_options', $options);
    //error_log('MXChat Save: Update result: ' . ($updated ? 'success' : 'unchanged') . ' for field: ' . $name);
    //error_log('MXChat Save: Updated options array: ' . print_r($options, true));

    // Log the save action if debug mode is enabled
    if ( class_exists( 'MxChat_Admin' ) ) {
        MxChat_Admin::mxchat_log_debug(
            'settings_save',
            sprintf( 'Field saved: %s', $field_name ),
            array(
                'field'   => $field_name,
                'updated' => $updated,
            )
        );
    }

    // Always return success even if WordPress says nothing changed
    // (which happens when the value is the same as before)
    wp_send_json_success(['message' => esc_html__('Setting saved', 'mxchat')]);
}

/**
 * Save the selected bot for knowledge base operations
 */
public function mxchat_save_selected_bot() {
    // Check nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mxchat_save_setting_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $bot_id = isset($_POST['bot_id']) ? sanitize_key($_POST['bot_id']) : 'default';
    
    // Save as user meta for the current user
    $user_id = get_current_user_id();
    update_user_meta($user_id, 'mxchat_selected_knowledge_bot', $bot_id);
    
    // Also save as an option for site-wide default
    update_option('mxchat_current_knowledge_bot', $bot_id);
    
    // No cache clearing needed since we removed caching
    
    wp_send_json_success(array(
        'message' => 'Bot selection saved',
        'bot_id' => $bot_id
    ));
}

    /**
     * Handles AJAX request for saving chat settings
     */
     public function mxchat_save_prompts_setting_callback() {
         check_ajax_referer('mxchat_prompts_setting_nonce');

         if (!current_user_can('manage_options')) {
             wp_send_json_error(['message' => esc_html__('Unauthorized', 'mxchat')]);
         }

         $name = isset($_POST['name']) ? $_POST['name'] : '';
         $value = isset($_POST['value']) ? stripslashes($_POST['value']) : '';

         //error_log('[MXCHAT-PROMPTS] Saving setting: ' . $name . ' = ' . $value);

         if (empty($name)) {
             wp_send_json_error(['message' => esc_html__('Invalid field name', 'mxchat')]);
         }

// Handle Pinecone settings - BYPASS WORDPRESS SANITIZATION
if (strpos($name, 'mxchat_pinecone_addon_options') !== false) {
    //error_log('[MXCHAT-PROMPTS] Processing Pinecone setting: ' . $name);

    // Extract the field name
    if (preg_match('/mxchat_pinecone_addon_options\[([^\]]+)\]/', $name, $matches)) {
        $field_name = $matches[1];
        //error_log('[MXCHAT-PROMPTS] Extracted field name: ' . $field_name);

        // Get current options directly from database - NO WordPress filters
        global $wpdb;
        $current_options_raw = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                'mxchat_pinecone_addon_options'
            )
        );

        // FIX: Handle the case where the option doesn't exist yet
        if ($current_options_raw === null) {
            // Option doesn't exist, create it with default values
            $current_options = array(
                'mxchat_use_pinecone' => '0',
                'mxchat_pinecone_api_key' => '',
                'mxchat_pinecone_host' => '',
                'mxchat_pinecone_index' => '',
                'mxchat_pinecone_environment' => ''
            );
            //error_log('[MXCHAT-PROMPTS] Option does not exist, creating with defaults');
        } else {
            // Unserialize the raw data
            $current_options = maybe_unserialize($current_options_raw);
            if (!is_array($current_options)) {
                // Fallback to defaults if unserialization fails
                $current_options = array(
                    'mxchat_use_pinecone' => '0',
                    'mxchat_pinecone_api_key' => '',
                    'mxchat_pinecone_host' => '',
                    'mxchat_pinecone_index' => '',
                    'mxchat_pinecone_environment' => ''
                );
                //error_log('[MXCHAT-PROMPTS] Failed to unserialize, using defaults');
            }
        }

        //error_log('[MXCHAT-PROMPTS] Current options from DB: ' . print_r($current_options, true));

        // Update the specific field with proper sanitization
        switch ($field_name) {
            case 'mxchat_use_pinecone':
                $new_value = ($value === '1') ? '1' : '0';
                break;
            case 'mxchat_pinecone_api_key':
            case 'mxchat_pinecone_host':
            case 'mxchat_pinecone_index':
            case 'mxchat_pinecone_environment':
                $new_value = sanitize_text_field($value);
                if ($field_name === 'mxchat_pinecone_host') {
                    $new_value = str_replace(['https://', 'http://'], '', $new_value);
                }
                break;
            default:
                wp_send_json_error(['message' => esc_html__('Unknown Pinecone field', 'mxchat')]);
        }

        $current_options[$field_name] = $new_value;
        //error_log('[MXCHAT-PROMPTS] New value for ' . $field_name . ': "' . $new_value . '"');
        //error_log('[MXCHAT-PROMPTS] Updated options: ' . print_r($current_options, true));

        // Save directly to database to bypass WordPress sanitization
        $serialized_options = maybe_serialize($current_options);
        
        // FIX: Use INSERT ... ON DUPLICATE KEY UPDATE or separate INSERT/UPDATE logic
        $option_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s",
                'mxchat_pinecone_addon_options'
            )
        );

        if ($option_exists > 0) {
            // Update existing option
            $save_result = $wpdb->update(
                $wpdb->options,
                array('option_value' => $serialized_options),
                array('option_name' => 'mxchat_pinecone_addon_options'),
                array('%s'),
                array('%s')
            );
            //error_log('[MXCHAT-PROMPTS] Updated existing option, result: ' . ($save_result !== false ? 'SUCCESS' : 'FAILED'));
        } else {
            // Insert new option
            $save_result = $wpdb->insert(
                $wpdb->options,
                array(
                    'option_name' => 'mxchat_pinecone_addon_options',
                    'option_value' => $serialized_options,
                    'autoload' => 'yes'
                ),
                array('%s', '%s', '%s')
            );
            //error_log('[MXCHAT-PROMPTS] Inserted new option, result: ' . ($save_result !== false ? 'SUCCESS' : 'FAILED'));
        }

        // Clear any WordPress option cache to ensure get_option() returns fresh data
        wp_cache_delete('mxchat_pinecone_addon_options', 'options');

        // IMPROVED VERIFICATION - Check if the database operation succeeded
        if ($save_result !== false) {
            // Double-check by reading fresh from database
            $verification_raw = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                    'mxchat_pinecone_addon_options'
                )
            );
            $verification_options = maybe_unserialize($verification_raw);
            $verified_value = isset($verification_options[$field_name]) ? $verification_options[$field_name] : 'NOT_FOUND';

            //error_log('[MXCHAT-PROMPTS] Final verification - Expected: "' . $new_value . '", Got: "' . $verified_value . '"');

            // Use loose comparison (==) instead of strict (===) to avoid type issues
            if ($verified_value == $new_value || $save_result > 0) {
                wp_send_json_success(['message' => esc_html__('Pinecone setting saved', 'mxchat')]);
            } else {
                // Still return success if the DB operation worked, even if verification is quirky
                //error_log('[MXCHAT-PROMPTS] Verification mismatch but DB operation succeeded');
                wp_send_json_success(['message' => esc_html__('Pinecone setting saved (DB success)', 'mxchat')]);
            }
        } else {
            wp_send_json_error(['message' => esc_html__('Database save failed', 'mxchat')]);
        }
    } else {
        wp_send_json_error(['message' => esc_html__('Invalid field name format', 'mxchat')]);
    }

    return; // Exit here for Pinecone settings
}
         // Handle auto-sync settings (existing functionality)
         if (strpos($name, 'mxchat_auto_sync_') === 0) {
             $value = ($value === 'on' || $value === '1') ? '1' : '0';
             $updated = update_option($name, $value);

             if ($updated || get_option($name) === $value) {
                 wp_send_json_success(['message' => esc_html__('Auto-sync setting saved', 'mxchat')]);
             } else {
                 wp_send_json_error(['message' => esc_html__('No changes detected', 'mxchat')]);
             }
         }

         // Handle chunking settings - use direct DB access to bypass WordPress filters
         if (strpos($name, 'mxchat_chunk') === 0 || $name === 'mxchat_chunking_enabled') {
             global $wpdb;

             // Get current options directly from database
             $current_options_raw = $wpdb->get_var(
                 $wpdb->prepare(
                     "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                     'mxchat_options'
                 )
             );

             $options = $current_options_raw !== null ? maybe_unserialize($current_options_raw) : array();
             if (!is_array($options)) {
                 $options = array();
             }

             // Update the specific chunking field
             if ($name === 'mxchat_chunking_enabled') {
                 $options['chunking_enabled'] = in_array($value, array('on', '1', 'true', true), true);
             } elseif ($name === 'mxchat_chunk_size') {
                 $options['chunk_size'] = max(1000, min(10000, intval($value)));
             }

             // Save directly to database
             $serialized_options = maybe_serialize($options);

             $option_exists = $wpdb->get_var(
                 $wpdb->prepare(
                     "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s",
                     'mxchat_options'
                 )
             );

             if ($option_exists > 0) {
                 $save_result = $wpdb->update(
                     $wpdb->options,
                     array('option_value' => $serialized_options),
                     array('option_name' => 'mxchat_options'),
                     array('%s'),
                     array('%s')
                 );
             } else {
                 $save_result = $wpdb->insert(
                     $wpdb->options,
                     array(
                         'option_name' => 'mxchat_options',
                         'option_value' => $serialized_options,
                         'autoload' => 'yes'
                     ),
                     array('%s', '%s', '%s')
                 );
             }

             // Clear object cache for this option
             wp_cache_delete('mxchat_options', 'options');

             if ($save_result !== false) {
                 wp_send_json_success(['message' => esc_html__('Chunking setting saved', 'mxchat')]);
             } else {
                 wp_send_json_error(['message' => esc_html__('Failed to save chunking setting', 'mxchat')]);
             }
             return;
         }

         // Handle ACF field exclusion toggles
         if (strpos($name, 'mxchat_acf_field_') === 0) {
             // Extract field name from the input name (e.g., mxchat_acf_field_private_notes -> private_notes)
             $field_name = str_replace('mxchat_acf_field_', '', $name);
             $is_enabled = ($value === 'on' || $value === '1');

             // Get current excluded fields
             $excluded_fields = get_option('mxchat_acf_excluded_fields', array());
             if (!is_array($excluded_fields)) {
                 $excluded_fields = array();
             }

             if ($is_enabled) {
                 // Remove from exclusion list (field should be included)
                 $excluded_fields = array_values(array_diff($excluded_fields, array($field_name)));
             } else {
                 // Add to exclusion list (field should be excluded)
                 if (!in_array($field_name, $excluded_fields)) {
                     $excluded_fields[] = $field_name;
                 }
             }

             $updated = update_option('mxchat_acf_excluded_fields', $excluded_fields);

             if ($updated || true) { // Always report success since the state may already be correct
                 wp_send_json_success([
                     'message' => $is_enabled
                         ? sprintf(esc_html__('Field "%s" will be included in imports', 'mxchat'), $field_name)
                         : sprintf(esc_html__('Field "%s" will be excluded from imports', 'mxchat'), $field_name)
                 ]);
             } else {
                 wp_send_json_error(['message' => esc_html__('Failed to save ACF field setting', 'mxchat')]);
             }
             return;
         }

         // Handle custom post meta whitelist
         if ($name === 'mxchat_custom_meta_whitelist') {
             $updated = update_option('mxchat_custom_meta_whitelist', sanitize_textarea_field($value));

             if ($updated || true) { // Always report success since the state may already be correct
                 wp_send_json_success([
                     'message' => esc_html__('Custom meta whitelist saved', 'mxchat')
                 ]);
             } else {
                 wp_send_json_error(['message' => esc_html__('Failed to save custom meta whitelist', 'mxchat')]);
             }
             return;
         }

         // Handle other prompts options
         $options = get_option('mxchat_prompts_options', []);
         $options[$name] = $value;
         $updated = update_option('mxchat_prompts_options', $options);

         if ($updated) {
             wp_send_json_success(['message' => esc_html__('Setting saved', 'mxchat')]);
         } else {
             wp_send_json_error(['message' => esc_html__('No changes detected', 'mxchat')]);
         }
     }


    /**
     * Handles AJAX request for Pinecone settings migration
     */
     public function ajax_migrate_pinecone_settings() {
         // Verify nonce
         if (!wp_verify_nonce($_POST['_ajax_nonce'] ?? '', 'mxchat_save_setting_nonce')) {
             wp_send_json_error('Invalid nonce');
         }

         // Check permissions
         if (!current_user_can('manage_options')) {
             wp_send_json_error('Unauthorized access');
         }

         // Check if old Pinecone addon options exist
         $old_options = get_option('mxchat_pinecone_addon_options', array());

         if (empty($old_options)) {
             wp_send_json_success(array('migrated' => false, 'message' => 'No old settings found'));
         }

         // Get current core plugin options
         $current_options = get_option('mxchat_pinecone_addon_options', array());

         // Only migrate if core options are empty or if explicitly requested
         $should_migrate = empty($current_options) ||
                          (empty($current_options['mxchat_pinecone_api_key']) && !empty($old_options['mxchat_pinecone_api_key']));

         if ($should_migrate) {
             // Migrate settings with proper sanitization
             $migrated_options = array(
                 'mxchat_use_pinecone' => $old_options['mxchat_use_pinecone'] ?? '0',
                 'mxchat_pinecone_api_key' => sanitize_text_field($old_options['mxchat_pinecone_api_key'] ?? ''),
                 'mxchat_pinecone_host' => sanitize_text_field($old_options['mxchat_pinecone_host'] ?? ''),
                 'mxchat_pinecone_index' => sanitize_text_field($old_options['mxchat_pinecone_index'] ?? ''),
                 'mxchat_pinecone_environment' => sanitize_text_field($old_options['mxchat_pinecone_environment'] ?? '')
             );

             update_option('mxchat_pinecone_addon_options', $migrated_options);

             wp_send_json_success(array(
                 'migrated' => true,
                 'message' => 'Settings migrated successfully from Pinecone add-on'
             ));
         } else {
             wp_send_json_success(array(
                 'migrated' => false,
                 'message' => 'Settings already exist in core plugin'
             ));
         }
     }


    // ========================================
    // LICENSE AJAX HANDLERS
    // ========================================

/**
 * Validates and activates chat license via AJAX
 */
public function mxchat_handle_activate_license() {
    // Check nonce
    if (!check_ajax_referer('mxchat_activate_license_nonce', 'security', false)) {
        wp_send_json_error(esc_html__('Invalid security token', 'mxchat'));
        return;
    }
    
    // Verify user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(esc_html__('Unauthorized access', 'mxchat'));
        return;
    }
    
    $license_key = isset($_POST['mxchat_activation_key']) ? sanitize_text_field($_POST['mxchat_activation_key']) : '';
    $customer_email = isset($_POST['mxchat_pro_email']) ? sanitize_email($_POST['mxchat_pro_email']) : '';
    
    if (empty($license_key) || empty($customer_email)) {
        wp_send_json_error(esc_html__('Email or License Key is missing', 'mxchat'));
        return;
    }
    
    $product_id = 'MxChatPRO';
    $domain = parse_url(home_url(), PHP_URL_HOST); // Get the current domain
    
    // Call WooCommerce Software API for activation (not just validation)
    $response = wp_remote_get(
        add_query_arg(
            array(
                'wc-api' => 'software-api',
                'request' => 'activation',
                'email' => $customer_email,
                'license_key' => $license_key,
                'product_id' => $product_id,
                'instance' => $domain, // THIS IS KEY - include the domain as instance
                'platform' => 'wordpress' // Optional but good to include
            ),
            'https://mxchat.ai/'
        ),
        array(
            'timeout' => 60,
            'sslverify' => true
        )
    );
    
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        //error_log('MxChat License Activation Error: ' . $error_message);
        wp_send_json_error(esc_html__('Activation failed due to a server error: ', 'mxchat') . $error_message);
        return;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    // Log response for debugging
    //error_log('MxChat License Response Code: ' . $response_code);
    //error_log('MxChat License Response Body: ' . $body);
    
    if ($response_code !== 200) {
        wp_send_json_error(esc_html__('Server returned error code: ', 'mxchat') . $response_code);
        return;
    }
    
    $data = json_decode($body);
    
    if ($data && isset($data->activated) && $data->activated) {
        // Success - save local options
        update_option('mxchat_license_status', 'active');
        update_option('mxchat_pro_email', $customer_email);
        update_option('mxchat_activation_key', $license_key);
        delete_option('mxchat_license_error');
        
        // Also track on your website (this is your existing domain tracking)
        $this->track_domain_on_website($license_key, $customer_email, $domain);
        
        wp_send_json_success(array('message' => esc_html__('License activated successfully', 'mxchat')));
    } else {
        $error_message = isset($data->error) ? $data->error : esc_html__('Activation failed', 'mxchat');
        update_option('mxchat_license_status', 'inactive');
        update_option('mxchat_license_error', $error_message);
        
        //error_log('MxChat Activation failed: ' . $error_message);
        wp_send_json_error($error_message);
    }
}

/**
 * Track domain on your website (separate from WooCommerce activation)
 */
private function track_domain_on_website($license_key, $email, $domain) {
    // This calls your website's tracking API
    wp_remote_post('https://mxchat.ai/mxchat-api/activate-license', array(
        'body' => array(
            'mxchat_pro_email' => $email,
            'mxchat_activation_key' => $license_key,
            'domain' => $domain
        ),
        'timeout' => 10,
        'sslverify' => true
    ));
}

    /**
     * Validates license via AJAX with email and key
     */
    public function mxchat_check_license_status() {
        // Verify nonce
        if (!check_ajax_referer('mxchat_activate_license_nonce', 'security', false)) {
            wp_send_json_error('Security check failed');
            return;
        }
    
        // Add isset checks for safety
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
    
        // Check if this license is actually active in your system
        $is_active = (get_option('mxchat_license_status') === 'active' &&
                      get_option('mxchat_pro_email') === $email &&
                      get_option('mxchat_activation_key') === $key);
    
        wp_send_json(array(
            'is_active' => $is_active
        ));
    }
     
/**
 * Handle license deactivation - Complete version for plugin
 */
function mxchat_deactivate_license() {
    // Add debugging
    //error_log('MxChat deactivate function called');
    
    // Check nonce
    if (!check_ajax_referer('mxchat_activate_license_nonce', 'security', false)) {
        //error_log('MxChat deactivate: Nonce check failed');
        wp_send_json_error('Security check failed.');
        return;
    }
    
    //error_log('MxChat deactivate: Nonce check passed');
    
    $license_key = get_option('mxchat_activation_key');
    $email = get_option('mxchat_pro_email');
    $domain = parse_url(home_url(), PHP_URL_HOST);
    
    //error_log('MxChat deactivate: License: ' . $license_key . ', Email: ' . $email . ', Domain: ' . $domain);
    
    if (empty($license_key) || empty($email)) {
        //error_log('MxChat deactivate: No active license found');
        wp_send_json_error('No active license found.');
        return;
    }
    
    // Clear local license data first
    delete_option('mxchat_license_status');
    delete_option('mxchat_pro_email');
    delete_option('mxchat_activation_key');
    delete_option('mxchat_license_error');
    
    //error_log('MxChat deactivate: Local data cleared');
    
    // Notify your website's API to properly deactivate
    $response = wp_remote_post('https://mxchat.ai/mxchat-api/deactivate-license', array(
        'body' => array(
            'license_key' => $license_key,
            'email' => $email,
            'domain' => $domain
        ),
        'timeout' => 15,
        'sslverify' => true
    ));
    
    if (is_wp_error($response)) {
        //error_log('MxChat deactivate: Server error - ' . $response->get_error_message());
        wp_send_json_success(array(
            'message' => 'License deactivated locally. Server could not be contacted to free activation slot.',
            'server_notified' => false
        ));
        return;
    }
    
    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);
    
    //error_log('MxChat deactivate: Server response - ' . $response_body);
    
    if (isset($response_data['success']) && $response_data['success']) {
        //error_log('MxChat deactivate: Success with server notification');
        wp_send_json_success(array(
            'message' => 'License deactivated successfully. Activation slot has been freed up.',
            'server_notified' => true
        ));
    } else {
        //error_log('MxChat deactivate: Server responded but deactivation may have failed');
        wp_send_json_success(array(
            'message' => 'License deactivated locally. Please check your account dashboard to verify the activation was freed.',
            'server_notified' => false
        ));
    }
}


    // ========================================
    // ACTIONS & INTENTS AJAX HANDLERS
    // ========================================

    /**
     * Validates nonce and returns JSON error on failure
     */
     public function mxchat_toggle_action() {
         // Check nonce
         if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mxchat_actions_nonce')) {
             wp_send_json_error(array('message' => 'Security check failed'));
             return;
         }

         // Check permissions
         if (!current_user_can('manage_options')) {
             wp_send_json_error(array('message' => 'Permission denied'));
             return;
         }

         // Validate params
         $intent_id = isset($_POST['intent_id']) ? intval($_POST['intent_id']) : 0;
         $enabled = isset($_POST['enabled']) ? (bool)$_POST['enabled'] : false;

         if (!$intent_id) {
             wp_send_json_error(array('message' => 'Invalid action ID'));
             return;
         }

         // Update the intent/action status in the database
         global $wpdb;
         $table_name = $wpdb->prefix . 'mxchat_intents';

         // Using the 'enabled' field - add this field if it doesn't exist
         $result = $wpdb->update(
             $table_name,
             array('enabled' => $enabled ? 1 : 0),
             array('id' => $intent_id),
             array('%d'),
             array('%d')
         );

         if ($result === false) {
             wp_send_json_error(array('message' => 'Database error'));
             return;
         }

         wp_send_json_success();
     }


    /**
     * Validates permissions for AJAX request handling
     */
     public function mxchat_update_intent_threshold() {
         // Check permissions
         if (!current_user_can('manage_options')) {
             if (wp_doing_ajax()) {
                 wp_send_json_error(array('message' => 'Unauthorized user'));
                 return;
             }
             wp_die(esc_html__('Unauthorized user', 'mxchat'));
         }

         // Verify nonce
         check_admin_referer('mxchat_update_intent_threshold_nonce');

         // Process the update if we have valid data
         if (isset($_POST['intent_id'], $_POST['intent_threshold'])) {
             global $wpdb;
             $table_name = $wpdb->prefix . 'mxchat_intents';
             $intent_id = intval($_POST['intent_id']);
             $threshold_percentage = max(70, min(95, intval($_POST['intent_threshold'])));
             $similarity_threshold = $threshold_percentage / 100;

             $result = $wpdb->update(
                 $table_name,
                 ['similarity_threshold' => $similarity_threshold],
                 ['id' => $intent_id],
                 ['%f'],
                 ['%d']
             );

             // Handle AJAX requests
             if (wp_doing_ajax()) {
                 if ($result === false) {
                     wp_send_json_error(array('message' => 'Failed to update threshold'));
                 } else {
                     wp_send_json_success(array('threshold' => $threshold_percentage));
                 }
                 return;
             }
         }

         // Redirect for regular form submissions
         wp_safe_redirect(admin_url('admin.php?page=mxchat-actions&updated=true'));
         exit;
     }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Returns a specific nonce action string
     */
     private function mxchat_get_nonce_action() {
         return 'mxchat_license_nonce';
     }

    /**
     * Check API key status for all providers
     */
    public function mxchat_check_api_keys() {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mxchat_save_setting_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Get current options
        $options = get_option('mxchat_options', array());

        // Check which API keys are present
        $api_key_status = array(
            'openai' => !empty($options['api_key']),
            'claude' => !empty($options['claude_api_key']),
            'xai' => !empty($options['xai_api_key']),
            'deepseek' => !empty($options['deepseek_api_key']),
            'gemini' => !empty($options['gemini_api_key']),
            'openrouter' => !empty($options['openrouter_api_key']),
            'voyage' => !empty($options['voyage_api_key'])
        );

        wp_send_json_success($api_key_status);
    }

    // ========================================
    // DEBUG & OPTIMIZATION AJAX HANDLERS
    // ========================================

    /**
     * Toggle debug mode on/off
     */
    public function mxchat_toggle_debug_mode_callback() {
        // Verify nonce
        if ( ! check_ajax_referer( 'mxchat_save_setting_nonce', '_ajax_nonce', false ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'mxchat' ) ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'mxchat' ) ) );
        }

        $enabled = isset( $_POST['enabled'] ) && $_POST['enabled'] === 'on';

        $options = get_option( 'mxchat_options', array() );

        if ( $enabled ) {
            $options['debug_mode'] = 'on';
            update_option( 'mxchat_options', $options );
            MxChat_Admin::mxchat_log_debug( 'debug_mode', 'Debug mode enabled' );
        } else {
            // Log before disabling
            MxChat_Admin::mxchat_log_debug( 'debug_mode', 'Debug mode disabled' );
            $options['debug_mode'] = 'off';
            update_option( 'mxchat_options', $options );
        }

        wp_send_json_success( array(
            'message' => $enabled ? esc_html__( 'Debug mode enabled', 'mxchat' ) : esc_html__( 'Debug mode disabled', 'mxchat' ),
            'enabled' => $enabled,
        ) );
    }

    /**
     * Get the debug log entries
     */
    public function mxchat_get_debug_log_callback() {
        // Verify nonce
        if ( ! check_ajax_referer( 'mxchat_save_setting_nonce', '_ajax_nonce', false ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'mxchat' ) ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'mxchat' ) ) );
        }

        $log = MxChat_Admin::mxchat_get_debug_log();

        wp_send_json_success( array(
            'log'   => $log,
            'count' => count( $log ),
        ) );
    }

    /**
     * Clear the debug log
     */
    public function mxchat_clear_debug_log_callback() {
        // Verify nonce
        if ( ! check_ajax_referer( 'mxchat_save_setting_nonce', '_ajax_nonce', false ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'mxchat' ) ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'mxchat' ) ) );
        }

        MxChat_Admin::mxchat_clear_debug_log();

        // Log that the log was cleared (this will be the first entry in the new log)
        MxChat_Admin::mxchat_log_debug( 'debug_log', 'Debug log cleared by user' );

        wp_send_json_success( array( 'message' => esc_html__( 'Debug log cleared', 'mxchat' ) ) );
    }

    /**
     * Export settings as JSON
     */
    public function mxchat_export_settings_callback() {
        // Verify nonce
        if ( ! check_ajax_referer( 'mxchat_save_setting_nonce', '_ajax_nonce', false ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'mxchat' ) ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'mxchat' ) ) );
        }

        $export = MxChat_Admin::mxchat_export_settings();

        // Log the export
        MxChat_Admin::mxchat_log_debug( 'settings_export', 'Settings exported by user' );

        wp_send_json_success( array(
            'settings' => $export,
            'filename' => 'mxchat-settings-' . gmdate( 'Y-m-d-His' ) . '.json',
        ) );
    }

    /**
     * Reset all settings to defaults
     */
    public function mxchat_reset_all_settings_callback() {
        // Verify nonce
        if ( ! check_ajax_referer( 'mxchat_save_setting_nonce', '_ajax_nonce', false ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed', 'mxchat' ) ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized', 'mxchat' ) ) );
        }

        // Require confirmation code
        $confirmation = isset( $_POST['confirmation'] ) ? sanitize_text_field( wp_unslash( $_POST['confirmation'] ) ) : '';

        if ( strtoupper( $confirmation ) !== 'RESET' ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid confirmation code. Please type RESET to confirm.', 'mxchat' ) ) );
        }

        // Perform the reset
        MxChat_Admin::mxchat_reset_all_settings();

        wp_send_json_success( array( 'message' => esc_html__( 'All settings have been reset to defaults. The page will reload.', 'mxchat' ) ) );
    }

}

// Initialize the AJAX handler
new MxChat_Ajax_Handler();
