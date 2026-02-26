<?php
/**
 * Plugin Name: Baachal AI Chatbot
 * Plugin URI: https://github.com/kshojib/baachal
 * Description: AI chatbot with multi-provider support (Gemini, OpenAI, Claude, Grok). Intelligent customer support with automatic content indexing and WooCommerce integration.
 * Version: 1.0.4
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Author: Shojib Khan
 * Author URI: https://www.shojibkhan.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: baachal
 * Domain Path: /languages
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 * 
 * @package Baachal
 * @author Shojib Khan
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BAACHAL_VERSION', '1.0.4');
define('BAACHAL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BAACHAL_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Include required files
require_once BAACHAL_PLUGIN_PATH . 'includes/content-indexer.php';

// Declare WooCommerce HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Main plugin class
class Baachal {
    
    /**
     * Holds rendered product cards for the current response cycle.
     *
     * @var string
     */
    private $current_product_cards_html = '';
    
    public function __construct() {
        // Allow other plugins to modify the hooks that get registered
        $hooks = apply_filters('baachal_register_hooks', array(
            'init' => array($this, 'init'),
            'wp_enqueue_scripts' => array($this, 'enqueue_scripts'),
            'wp_footer' => array($this, 'add_chatbot_html'),
            'wp_ajax_baachal_message' => array($this, 'handle_chatbot_message'),
            'wp_ajax_nopriv_baachal_message' => array($this, 'handle_chatbot_message'),
            'wp_ajax_baachal_get_chat_history' => array($this, 'get_chat_history'),
            'wp_ajax_nopriv_baachal_get_chat_history' => array($this, 'get_chat_history'),
            'wp_ajax_baachal_clear_chat_history' => array($this, 'clear_chat_history'),
            'wp_ajax_nopriv_baachal_clear_chat_history' => array($this, 'clear_chat_history'),
            'wp_ajax_clear_baachal_cache' => array($this, 'handle_clear_cache_ajax'),
            'wp_ajax_baachal_test_content_search' => array($this, 'handle_test_content_search'),
            'wp_ajax_baachal_reindex_content' => array($this, 'handle_reindex_content_ajax'),
            'admin_menu' => array($this, 'add_admin_menu'),
            'admin_init' => array($this, 'register_settings'),
            'admin_enqueue_scripts' => array($this, 'enqueue_admin_scripts'),
            'add_meta_boxes' => array($this, 'add_chat_meta_boxes')
        ));
        
        // Register all hooks
        foreach ($hooks as $hook => $callback) {
            add_action($hook, $callback);
        }
        
        // Add settings link to plugin page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_plugin'));
        register_uninstall_hook(__FILE__, array('Baachal', 'uninstall_plugin'));
        
        // Clear product cache when products are updated
        add_action('save_post', array($this, 'clear_product_cache_on_update'));
        add_action('delete_post', array($this, 'clear_product_cache_on_update'));
        add_action('wp_trash_post', array($this, 'clear_product_cache_on_update'));
        add_action('untrash_post', array($this, 'clear_product_cache_on_update'));
        
        // Clear cache when taxonomies are updated
        add_action('created_term', array($this, 'clear_dynamic_terms_cache'));
        add_action('edited_term', array($this, 'clear_dynamic_terms_cache'));
        add_action('delete_term', array($this, 'clear_dynamic_terms_cache'));
        
        // Clear cache when product attributes are updated
        add_action('woocommerce_attribute_added', array($this, 'clear_dynamic_terms_cache'));
        add_action('woocommerce_attribute_updated', array($this, 'clear_dynamic_terms_cache'));
        add_action('woocommerce_attribute_deleted', array($this, 'clear_dynamic_terms_cache'));
        
        // Allow other plugins to perform actions after plugin initialization
        do_action('baachal_init', $this);
    }
    
    public function clear_dynamic_terms_cache() {
        delete_transient('baachal_dynamic_terms');
    }
    
    public function clear_product_cache_on_update($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'product') {
            // Clear all product search cache
            global $wpdb;
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_baachal_products_%'));
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_baachal_products_%'));
            
            // Clear dynamic terms cache as product catalog has changed
            delete_transient('baachal_dynamic_terms');
        }
    }
    
    public function init() {
        // Allow other plugins to perform actions before initialization
        do_action('baachal_before_init');
        
        // Register custom post type for chat conversations
        $this->register_chat_post_type();
        
        // Allow other plugins to perform actions after initialization
        do_action('baachal_after_init');
    }
    
    public function activate_plugin() {
        // Register post type
        $this->register_chat_post_type();
        
        // Create content index table
        $indexer = new Baachal_Content_Indexer();
        $indexer->create_index_table();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Allow other plugins to perform activation tasks
        do_action('baachal_plugin_activated');
    }
    
    public function deactivate_plugin() {
        // Clear all transients
        $this->clear_all_transients();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Allow other plugins to perform deactivation tasks
        do_action('baachal_plugin_deactivated');
    }
    
    public static function uninstall_plugin() {
        // Only proceed if user has proper permissions
        if (!current_user_can('activate_plugins')) {
            return;
        }
        
        // Check if we should preserve data
        $preserve_data = get_option('baachal_preserve_data_on_uninstall', '1');
        
        if ($preserve_data !== '1') {
            global $wpdb;
            
            // Delete all plugin options
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'baachal_%'));
            
            // Delete content index table
            $index_table = $wpdb->prefix . 'baachal_content_index';
            $escaped_table = '`' . esc_sql($index_table) . '`';
            $wpdb->query("DROP TABLE IF EXISTS " . $escaped_table); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
            
            // Delete all conversations
            $conversations = get_posts(array(
                'post_type' => 'baachal_conversation',
                'numberposts' => -1,
                'post_status' => 'any'
            ));
            
            foreach ($conversations as $conversation) {
                wp_delete_post($conversation->ID, true);
            }
            
            // Clear all transients
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_baachal_%', '_transient_timeout_baachal_%'));
            
            // Allow other plugins to perform cleanup
            do_action('baachal_plugin_uninstalled');
        }
    }
    
    private function set_default_options() {
        // Handle migration from single-provider to multi-provider
        $this->migrate_provider_settings();
        
        // Set default options if they don't exist
        $defaults = array(
            'baachal_enabled' => '1',
            'baachal_welcome_message' => 'Hello! How can I help you today?',
            'baachal_title' => 'Baachal AI Bot',
            'baachal_ai_provider' => 'gemini',
            'baachal_ai_model' => 'gemini-2.5-flash',
            'baachal_position' => 'bottom-right',
            'baachal_size' => 'medium',
            'baachal_primary_color' => '#007cba',
            'baachal_secondary_color' => '#f0f0f1',
            'baachal_border_radius' => '12',
            'baachal_chat_height' => '400',
            'baachal_font_size' => '14',
            'baachal_animation_enabled' => '1',
            'baachal_woocommerce_integration' => '1',
            'baachal_message_persistence' => '1',
            'baachal_show_clear_history' => '1',
            'baachal_max_terms' => '50',
            'baachal_min_term_length' => '3',
            'baachal_cache_duration' => DAY_IN_SECONDS,
            'baachal_content_indexing_enabled' => '1',
            'baachal_auto_index' => '1',
            'baachal_content_max_results' => '5',
            'baachal_indexable_post_types' => array('post', 'page'),
            'baachal_preserve_data_on_uninstall' => '1'
        );
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                update_option($option, $value);
            }
        }
    }
    
    private function migrate_provider_settings() {
        // Check if this is an upgrade from single-provider version
        $old_gemini_model = get_option('baachal_gemini_model');
        $new_ai_model = get_option('baachal_ai_model');
        
        // If old model exists but new model doesn't, migrate
        if ($old_gemini_model && !$new_ai_model) {
            // Map old models to new models
            $model_mapping = array(
                'gemini-2.0-flash-exp' => 'gemini-2.5-flash',
                'gemini-1.5-flash' => 'gemini-2.5-flash',
                'gemini-1.5-pro' => 'gemini-2.5-pro',
                'gemini-pro' => 'gemini-2.5-flash'
            );
            
            $migrated_model = isset($model_mapping[$old_gemini_model]) ? $model_mapping[$old_gemini_model] : 'gemini-2.5-flash';
            update_option('baachal_ai_model', $migrated_model);
            update_option('baachal_ai_provider', 'gemini');
        }
        
        // Migrate old Gemini API key if it exists and provider is not set
        $old_api_key = get_option('baachal_gemini_api_key');
        $provider = get_option('baachal_ai_provider');
        
        if ($old_api_key && !$provider) {
            update_option('baachal_ai_provider', 'gemini');
        }
    }
    
    private function clear_all_transients() {
        global $wpdb;
        
        // Clear all Baachal-related transients
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_baachal_%'));
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", '_transient_timeout_baachal_%'));
        
        // Clear dynamic terms cache
        delete_transient('baachal_dynamic_terms');
    }
    
    public function register_chat_post_type() {
        $args = array(
            'label' => 'Baachal Conversations',
            'labels' => array(
                'name' => 'Baachal Conversations',
                'singular_name' => 'Baachal Conversation',
                'menu_name' => 'Baachal Conversations',
                'all_items' => 'All Conversations',
                'view_item' => 'View Conversation',
                'search_items' => 'Search Conversations',
                'not_found' => 'No conversations found',
                'not_found_in_trash' => 'No conversations found in trash'
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 26,
            'menu_icon' => 'dashicons-format-chat',
            'supports' => array('title', 'custom-fields'),
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => 'manage_options',
                'edit_posts' => 'manage_options',
                'edit_others_posts' => 'manage_options',
                'delete_posts' => 'manage_options',
                'delete_others_posts' => 'manage_options',
                'read_private_posts' => 'manage_options',
            ),
            'map_meta_cap' => true,
        );
        
        register_post_type('baachal_conversation', $args);
    }
    
    public function enqueue_scripts() {
        // Allow other plugins to modify script dependencies
        $js_dependencies = apply_filters('baachal_js_dependencies', array('jquery'));
        $css_dependencies = apply_filters('baachal_css_dependencies', array());

        wp_enqueue_script('baachal-js', BAACHAL_PLUGIN_URL . 'assets/chatbot.js', $js_dependencies, BAACHAL_VERSION, true);
        wp_enqueue_style('baachal-css', BAACHAL_PLUGIN_URL . 'assets/chatbot.css', $css_dependencies, BAACHAL_VERSION);

        // Add custom styling based on settings
        $this->add_custom_styles();

        // Allow other plugins to modify localized data
        $localized_data = apply_filters('baachal_localized_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('baachal_nonce'),
            'debug_mode' => get_option('baachal_debug_mode', '0'),
            'message_persistence' => get_option('baachal_message_persistence', '1'),
            'plugin_url' => BAACHAL_PLUGIN_URL
        ));

        // Localize script for AJAX
        wp_localize_script('baachal-js', 'baachal_ajax', $localized_data);

        // Allow other plugins to enqueue additional scripts/styles
        do_action('baachal_after_enqueue_scripts');
    }
    
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin's admin pages
        if ($hook !== 'settings_page_baachal-settings' && strpos($hook, 'baachal') === false) {
            return;
        }
        
        // Enqueue admin JavaScript
        wp_enqueue_script(
            'baachal-admin-js',
            BAACHAL_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            BAACHAL_VERSION,
            true
        );
        
        // Enqueue admin CSS
        wp_enqueue_style(
            'baachal-admin-css',
            BAACHAL_PLUGIN_URL . 'assets/admin.css',
            array(),
            BAACHAL_VERSION
        );
        
        // Localize admin script
        wp_localize_script('baachal-admin-js', 'baachal_admin', array(
            'reindex_confirm' => esc_html__('This will reindex all content. Continue?', 'baachal'),
            'indexing_text' => esc_html__('Indexing...', 'baachal'),
            'reindex_text' => esc_html__('Reindex All Content', 'baachal'),
            'reindex_nonce' => wp_create_nonce('baachal_reindex_content'),
            'test_search_nonce' => wp_create_nonce('baachal_test_search'),
            'clear_cache_confirm' => esc_html__('Are you sure you want to clear the cache?', 'baachal'),
            'clearing_text' => esc_html__('Clearing...', 'baachal'),
            'clear_cache_text' => esc_html__('Clear Cache', 'baachal'),
            'cache_cleared_text' => esc_html__('Cache cleared successfully!', 'baachal'),
            'clear_cache_nonce' => wp_create_nonce('clear_baachal_cache')
        ));
    }
    
    private function add_custom_styles() {
        // Get styling options
        $primary_color = get_option('baachal_primary_color', '#007cba');
        $secondary_color = get_option('baachal_secondary_color', '#f1f1f1');
        $border_radius = get_option('baachal_border_radius', 15);
        $chat_height = get_option('baachal_chat_height', 400);
        $font_size = get_option('baachal_font_size', 14);
        
        // Custom CSS using CSS custom properties
        $custom_css = "
            :root {
                --chatbot-primary-color: {$primary_color};
                --chatbot-secondary-color: {$secondary_color};
                --chatbot-border-radius: {$border_radius}px;
                --chatbot-font-size: {$font_size}px;
                --chatbot-chat-height: {$chat_height}px;
            }";

        // Allow other plugins to modify the custom CSS
        $custom_css = apply_filters('baachal_custom_css', $custom_css, array(
            'primary_color' => $primary_color,
            'secondary_color' => $secondary_color,
            'border_radius' => $border_radius,
            'chat_height' => $chat_height,
            'font_size' => $font_size
        ));

        // Add the custom CSS as inline styles to the main chatbot stylesheet
        wp_add_inline_style('baachal-css', $custom_css);
    }
    
    private function darken_color($hex, $percent) {
        // Remove the hash if present
        $hex = str_replace('#', '', $hex);
        
        // Convert to RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        // Darken
        $r = max(0, $r - ($r * $percent / 100));
        $g = max(0, $g - ($g * $percent / 100));
        $b = max(0, $b - ($b * $percent / 100));
        
        // Convert back to hex
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    
    private function lighten_color($hex, $percent) {
        // Remove the hash if present
        $hex = str_replace('#', '', $hex);
        
        // Convert to RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        // Lighten
        $r = min(255, $r + ((255 - $r) * $percent / 100));
        $g = min(255, $g + ((255 - $g) * $percent / 100));
        $b = min(255, $b + ((255 - $b) * $percent / 100));
        
        // Convert back to hex
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    
    public function add_chatbot_html() {
        $is_enabled = get_option('baachal_enabled', '1');
        
        // Allow other plugins to override the enabled state
        $is_enabled = apply_filters('baachal_is_enabled', $is_enabled);
        
        if ($is_enabled !== '1') {
            return;
        }
        
        // Allow other plugins to add content before widget
        do_action('baachal_before_widget_render');
        
        include BAACHAL_PLUGIN_PATH . 'templates/chatbot-widget.php';
        
        // Allow other plugins to add content after widget
        do_action('baachal_after_widget_render');
    }
    
    public function handle_chatbot_message() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'baachal_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!isset($_POST['message'])) {
            wp_die('Message is required');
        }
        
        $message = sanitize_text_field(wp_unslash($_POST['message']));
        
        // Validate message
        if (empty(trim($message))) {
            wp_send_json_error('Message cannot be empty');
            return;
        }
        
        if (strlen($message) > 1000) {
            wp_send_json_error('Message is too long (maximum 1000 characters)');
            return;
        }
        
        // Get session ID from frontend (preferred) or fallback to server-side generation
        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : null;
        $session_id = $this->get_chat_session_id($session_id);
        
        // Reset product card state for this request cycle.
        $this->current_product_cards_html = '';
        
        // Allow other plugins to modify the user message before processing
        // Only pass relevant context data instead of entire $_POST
        $message_context = array(
            'nonce' => isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '',
            'original_message' => $message,
            'session_id' => $session_id
        );
        $message = apply_filters('baachal_before_process_message', $message, $message_context);
        
        // Allow other plugins to handle the message entirely
        $custom_response = apply_filters('baachal_custom_message_handler', null, $message, $message_context);
        
        if ($custom_response !== null) {
            // Custom handler provided a response
            if (is_array($custom_response) && isset($custom_response['success'])) {
                if ($custom_response['success']) {
                    wp_send_json_success($custom_response['data']);
                } else {
                    wp_send_json_error($custom_response['error']);
                }
            } else {
                wp_send_json_success($custom_response);
            }
            return;
        }
        
        $provider = get_option('baachal_ai_provider', 'gemini');
        $api_key = get_option('baachal_' . $provider . '_api_key');
        
        if (empty($api_key)) {
            wp_send_json_error('API key not configured for ' . ucfirst($provider));
            return;
        }
        
        // Allow other plugins to modify API parameters
        // Only pass the API parameters and essential context
        $api_context = array(
            'provider' => $provider,
            'message_length' => strlen($message)
        );
        
        // Get conversation history if conversation memory is enabled
        $conversation_history = array();
        if (get_option('baachal_conversation_memory', '0') === '1') {
            $conversation_history = $this->get_conversation_history($session_id);
        }
        
        $api_params = apply_filters('baachal_api_params', array(
            'message' => $message,
            'conversation_history' => $conversation_history
        ), $api_context);
        
        $result = $this->call_ai_api($api_params['message'], $api_params['conversation_history']);
        
        if ($result['success']) {
            // Allow other plugins to modify the response before saving/sending
            $bot_response = apply_filters('baachal_bot_response', $result['data'], $message, $result);
            
            if (!empty($this->current_product_cards_html)) {
                $bot_response .= "\n\n" . $this->current_product_cards_html;
            }
            
            // Save both user message and bot response
            $this->save_chat_message($message, 'user', $session_id);
            $this->save_chat_message($bot_response, 'bot', $session_id);
            
            // Allow other plugins to perform actions after successful response
            do_action('baachal_after_successful_response', $bot_response, $message, $result);
            
            wp_send_json_success($bot_response);
        } else {
            // Allow other plugins to modify error response
            $error_response = apply_filters('baachal_error_response', $result['error'], $message, $result);
            
            // Allow other plugins to perform actions after error
            do_action('baachal_after_error_response', $error_response, $message, $result);
            
            wp_send_json_error($error_response);
        }
    }
    
    private function get_chat_session_id($provided_session_id = null) {
        // If a session ID is provided (from frontend), validate and use it
        if (!empty($provided_session_id)) {
            // Validate session ID format
            if (preg_match('/^chat_[a-f0-9\-]{36}$/', $provided_session_id)) {
                return sanitize_text_field($provided_session_id);
            }
        }
        
        // Generate a new session ID if none provided or invalid
        // This ensures we never use PHP sessions
        return 'chat_' . wp_generate_uuid4();
    }
    
    private function save_chat_message($message, $type, $session_id = null) {
        // Allow other plugins to prevent message saving
        $should_save = apply_filters('baachal_should_save_message', true, $message, $type);
        
        if (!$should_save) {
            return;
        }
        
        if (get_option('baachal_message_persistence', '1') !== '1') {
            return; // Don't save if persistence is disabled
        }
        
        // Use provided session ID or fall back to getting one
        if (empty($session_id)) {
            $session_id = $this->get_chat_session_id();
        }
        $user_id = get_current_user_id();
        
        // Allow other plugins to modify message data before saving
        $message_data = apply_filters('baachal_before_save_message', array(
            'message' => $message,
            'type' => $type,
            'session_id' => $session_id,
            'user_id' => $user_id,
            'timestamp' => current_time('mysql')
        ));
        
        // Find or create conversation post
        $conversation_id = $this->get_or_create_conversation($message_data['session_id'], $message_data['user_id']);
        
        if ($conversation_id) {
            // Get existing messages
            $messages = get_post_meta($conversation_id, '_chat_messages', true);
            if (!is_array($messages)) {
                $messages = array();
            }
            
            // Add new message
            $messages[] = $message_data;
            
            // Allow other plugins to modify the message limit
            $message_limit = apply_filters('baachal_message_limit', 100);
            
            // Keep only last X messages per conversation
            if (count($messages) > $message_limit) {
                $messages = array_slice($messages, -$message_limit);
            }
            
            // Update messages
            update_post_meta($conversation_id, '_chat_messages', $messages);
            
            // Update conversation title if it's just the default
            $current_title = get_the_title($conversation_id);
            if (strpos($current_title, 'Chat Session') !== false && $message_data['type'] === 'user') {
                $new_title = apply_filters('baachal_conversation_title', 'Chat: ' . wp_trim_words($message_data['message'], 5, '...'), $message_data['message'], $conversation_id);
                wp_update_post(array(
                    'ID' => $conversation_id,
                    'post_title' => $new_title
                ));
            }
            
            // Allow other plugins to perform actions after saving message
            do_action('baachal_after_save_message', $message_data, $conversation_id, $messages);
        }
    }
    
    private function get_or_create_conversation($session_id, $user_id = 0) {
        // Look for existing conversation with this session ID
        $existing = get_posts(array(
            'post_type' => 'baachal_conversation',
            'meta_query' => array(
                array(
                    'key' => '_session_id',
                    'value' => $session_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ));
        
        if (!empty($existing)) {
            return $existing[0]->ID;
        }
        
        // Create new conversation
        $conversation_id = wp_insert_post(array(
            'post_type' => 'baachal_conversation',
            'post_title' => 'Chat Session ' . gmdate('Y-m-d H:i:s'),
            'post_status' => 'publish',
            'post_author' => $user_id ?: 1 // Use user ID or default to admin
        ));
        
        if ($conversation_id && !is_wp_error($conversation_id)) {
            // Store session metadata
            update_post_meta($conversation_id, '_session_id', $session_id);
            update_post_meta($conversation_id, '_user_id', $user_id);
            update_post_meta($conversation_id, '_user_ip', isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '');
            update_post_meta($conversation_id, '_user_agent', isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '');
            update_post_meta($conversation_id, '_chat_messages', array());
            
            return $conversation_id;
        }
        
        return false;
    }
    
    private function get_conversation_history($session_id) {
        // Get conversation for this session
        $conversation = get_posts(array(
            'post_type' => 'baachal_conversation',
            'meta_query' => array(
                array(
                    'key' => '_session_id',
                    'value' => $session_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ));
        
        if (empty($conversation)) {
            return array();
        }
        
        $messages = get_post_meta($conversation[0]->ID, '_chat_messages', true);
        if (!is_array($messages)) {
            return array();
        }
        
        // Get memory limit setting (default 10 messages)
        $memory_limit = get_option('baachal_memory_limit', 10);
        
        // Allow other plugins to modify memory limit dynamically
        $memory_limit = apply_filters('baachal_memory_limit', $memory_limit, $session_id);
        
        // Return only the most recent messages up to the limit
        // Exclude the current user message that hasn't been saved yet
        $recent_messages = array_slice($messages, -$memory_limit);
        
        // Filter to return only user and bot messages with proper format
        $formatted_history = array();
        foreach ($recent_messages as $msg) {
            if (isset($msg['type']) && isset($msg['message']) && 
                in_array($msg['type'], array('user', 'bot'))) {
                $formatted_history[] = array(
                    'role' => $msg['type'] === 'user' ? 'user' : 'assistant',
                    'content' => $msg['message']
                );
            }
        }
        
        // Allow other plugins to filter conversation history
        $formatted_history = apply_filters('baachal_conversation_history', $formatted_history, $session_id);
        
        return $formatted_history;
    }
    
    public function get_chat_history() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'baachal_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!isset($_POST['session_id'])) {
            wp_die('Session ID is required');
        }
        
        $session_id = sanitize_text_field(wp_unslash($_POST['session_id']));
        
        // Validate session ID format
        if (empty($session_id) || !preg_match('/^chat_[a-f0-9\-]{36}$/', $session_id)) {
            wp_send_json_error('Invalid session ID format');
            return;
        }
        
        // Get conversation for this session
        $conversation = get_posts(array(
            'post_type' => 'baachal_conversation',
            'meta_query' => array(
                array(
                    'key' => '_session_id',
                    'value' => $session_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ));
        
        if (!empty($conversation)) {
            $messages = get_post_meta($conversation[0]->ID, '_chat_messages', true);
            if (is_array($messages)) {
                wp_send_json_success($messages);
                return;
            }
        }
        
        wp_send_json_success(array());
    }
    
    public function handle_clear_cache_ajax() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'clear_baachal_cache')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Check if user has permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Clear the cache
        delete_transient('baachal_dynamic_terms');
        
        wp_send_json_success('Cache cleared successfully');
    }
    
    public function handle_test_content_search() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'baachal_test_search')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Check if user has permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        if (!isset($_POST['query'])) {
            wp_send_json_error('Query is required');
            return;
        }
        
        $query = sanitize_text_field(wp_unslash($_POST['query']));
        if (empty($query)) {
            wp_send_json_error('Query is required');
            return;
        }
        
        // Get content indexer instance and search
        $indexer = new Baachal_Content_Indexer();
        $results = $indexer->search_content($query, 10);
        
        wp_send_json_success(array('results' => $results));
    }
    
    public function handle_reindex_content_ajax() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'baachal_reindex_content')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Check if user has permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Get content indexer instance and reindex
        $indexer = new Baachal_Content_Indexer();
        $indexed_count = $indexer->index_all_content();
        
        wp_send_json_success(array('count' => $indexed_count));
    }
    
    public function clear_chat_history() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'baachal_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!isset($_POST['session_id'])) {
            wp_die('Session ID is required');
        }
        
        $session_id = sanitize_text_field(wp_unslash($_POST['session_id']));
        
        // Validate session ID format
        if (empty($session_id) || !preg_match('/^chat_[a-f0-9\-]{36}$/', $session_id)) {
            wp_send_json_error('Invalid session ID format');
            return;
        }
        
        // Get conversation for this session
        $conversation = get_posts(array(
            'post_type' => 'baachal_conversation',
            'meta_query' => array(
                array(
                    'key' => '_session_id',
                    'value' => $session_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ));
        
        if (!empty($conversation)) {
            // Clear messages but keep the conversation post
            update_post_meta($conversation[0]->ID, '_chat_messages', array());
            wp_send_json_success('Chat history cleared');
        } else {
            wp_send_json_success('No chat history found');
        }
    }
    
    private function call_ai_api($message, $conversation_history = array()) {
        $provider = get_option('baachal_ai_provider', 'gemini');
        $api_key = get_option('baachal_' . $provider . '_api_key', '');
        
        if (empty($api_key)) {
            return array(
                'success' => false,
                'error' => 'API key not configured for ' . ucfirst($provider)
            );
        }
        
        switch ($provider) {
            case 'gemini':
                return $this->call_gemini_api($message, $api_key, $conversation_history);
            case 'openai':
                return $this->call_openai_api($message, $api_key, $conversation_history);
            case 'claude':
                return $this->call_claude_api($message, $api_key, $conversation_history);
            case 'grok':
                return $this->call_grok_api($message, $api_key, $conversation_history);
            default:
                return array(
                    'success' => false,
                    'error' => 'Unsupported AI provider: ' . $provider
                );
        }
    }

    private function call_gemini_api($message, $api_key, $conversation_history = array()) {
        $website_context = $this->get_website_context($message);
        
        // Get selected model from settings
        $selected_model = get_option('baachal_ai_model', 'gemini-2.0-flash-exp');
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $selected_model . ':generateContent?key=' . $api_key;
        
        // Build conversation contents
        $contents = array();
        
        // Add system context as the first message if we have conversation history
        if (!empty($conversation_history)) {
            $contents[] = array(
                'parts' => array(
                    array('text' => $website_context)
                ),
                'role' => 'user'
            );
            $contents[] = array(
                'parts' => array(
                    array('text' => 'I understand. I will use this context to help answer questions about your website and products.')
                ),
                'role' => 'model'
            );
            
            // Add conversation history
            foreach ($conversation_history as $history_message) {
                $contents[] = array(
                    'parts' => array(
                        array('text' => $history_message['content'])
                    ),
                    'role' => $history_message['role'] === 'user' ? 'user' : 'model'
                );
            }
        }
        
        // Add current message
        if (!empty($conversation_history)) {
            // If we have history, just add the current message
            $contents[] = array(
                'parts' => array(
                    array('text' => $message)
                ),
                'role' => 'user'
            );
        } else {
            // If no history, include context with the message
            $full_prompt = $website_context . "\n\nUser question: " . $message;
            $contents[] = array(
                'parts' => array(
                    array('text' => $full_prompt)
                )
            );
        }
        
        $data = array(
            'contents' => $contents,
            'generationConfig' => array(
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 1024,
            )
        );
        
        $args = array(
            'body' => json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
            'user-agent' => 'WordPress/' . get_bloginfo('version') . ' Baachal/1.0.0'
        );
        
        $response = wp_remote_post($url, $args);
        
        // Handle network errors
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => 'Network error: ' . $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Handle success
        if ($status_code === 200) {
            $decoded = json_decode($body, true);
            
            if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
                return array(
                    'success' => true,
                    'data' => $decoded['candidates'][0]['content']['parts'][0]['text']
                );
            }
            
            // Check for safety filters
            if (isset($decoded['candidates'][0]['finishReason'])) {
                $finish_reason = $decoded['candidates'][0]['finishReason'];
                if ($finish_reason === 'SAFETY') {
                    return array(
                        'success' => false,
                        'error' => 'Response was filtered for safety reasons. Please try rephrasing your question.'
                    );
                }
            }
            
            return array(
                'success' => false,
                'error' => 'Unexpected response format from AI service'
            );
        }
        
        // Handle API errors
        $error_data = json_decode($body, true);
        $error_message = 'API error (HTTP ' . $status_code . ')';
        
        if (isset($error_data['error']['message'])) {
            $error_message .= ': ' . $error_data['error']['message'];
            
            // Provide helpful suggestions for common errors
            if (strpos($error_data['error']['message'], 'overloaded') !== false) {
                $error_message .= '. Please try again in a moment.';
            } elseif (strpos($error_data['error']['message'], 'quota') !== false || $status_code === 429) {
                $error_message = 'API quota exceeded. Please check your Google AI Studio usage or try again later.';
            } elseif ($status_code === 400) {
                $error_message .= '. Please check your API key configuration.';
            }
        }
        
        return array(
            'success' => false,
            'error' => $error_message
        );
    }

    private function call_openai_api($message, $api_key, $conversation_history = array()) {
        $website_context = $this->get_website_context($message);
        
        $selected_model = get_option('baachal_ai_model', 'gpt-5');
        $url = 'https://api.openai.com/v1/chat/completions';
        
        // Build messages array
        $messages = array();
        
        // Add system message with website context
        $messages[] = array(
            'role' => 'system',
            'content' => $website_context
        );
        
        // Add conversation history if available
        if (!empty($conversation_history)) {
            foreach ($conversation_history as $history_message) {
                $messages[] = array(
                    'role' => $history_message['role'] === 'user' ? 'user' : 'assistant',
                    'content' => $history_message['content']
                );
            }
        }
        
        // Add current user message
        $messages[] = array(
            'role' => 'user',
            'content' => $message
        );
        
        $data = array(
            'model' => $selected_model,
            'messages' => $messages,
            'max_tokens' => 1024,
            'temperature' => 0.7
        );
        
        $args = array(
            'body' => json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'timeout' => 30,
            'user-agent' => 'WordPress/' . get_bloginfo('version') . ' Baachal/1.0.0'
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => 'Network error: ' . $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code === 200) {
            $decoded = json_decode($body, true);
            
            if (isset($decoded['choices'][0]['message']['content'])) {
                return array(
                    'success' => true,
                    'data' => $decoded['choices'][0]['message']['content']
                );
            }
        }
        
        $error_data = json_decode($body, true);
        $error_message = 'OpenAI API error (HTTP ' . $status_code . ')';
        
        if (isset($error_data['error']['message'])) {
            $error_message .= ': ' . $error_data['error']['message'];
        }
        
        return array(
            'success' => false,
            'error' => $error_message
        );
    }

    private function call_claude_api($message, $api_key, $conversation_history = array()) {
        $website_context = $this->get_website_context($message);
        
        $selected_model = get_option('baachal_ai_model', 'claude-3-5-sonnet-20241022');
        $url = 'https://api.anthropic.com/v1/messages';
        
        // Build messages array
        $messages = array();
        
        // Add conversation history if available
        if (!empty($conversation_history)) {
            foreach ($conversation_history as $history_message) {
                $messages[] = array(
                    'role' => $history_message['role'],
                    'content' => $history_message['content']
                );
            }
        }
        
        // Add current user message
        $current_content = $message;
        if (empty($conversation_history)) {
            // If no history, include context with the first message
            $current_content = $website_context . "\n\nUser question: " . $message;
        }
        
        $messages[] = array(
            'role' => 'user',
            'content' => $current_content
        );
        
        $data = array(
            'model' => $selected_model,
            'max_tokens' => 1024,
            'messages' => $messages
        );
        
        // Add system message if we have conversation history
        if (!empty($conversation_history)) {
            $data['system'] = $website_context;
        }
        
        $args = array(
            'body' => json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01'
            ),
            'timeout' => 30,
            'user-agent' => 'WordPress/' . get_bloginfo('version') . ' Baachal/1.0.0'
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => 'Network error: ' . $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code === 200) {
            $decoded = json_decode($body, true);
            
            if (isset($decoded['content'][0]['text'])) {
                return array(
                    'success' => true,
                    'data' => $decoded['content'][0]['text']
                );
            }
        }
        
        $error_data = json_decode($body, true);
        $error_message = 'Claude API error (HTTP ' . $status_code . ')';
        
        if (isset($error_data['error']['message'])) {
            $error_message .= ': ' . $error_data['error']['message'];
        }
        
        return array(
            'success' => false,
            'error' => $error_message
        );
    }

    private function call_grok_api($message, $api_key, $conversation_history = array()) {
        $website_context = $this->get_website_context($message);
        
        $selected_model = get_option('baachal_ai_model', 'grok-beta');
        $url = 'https://api.x.ai/v1/chat/completions';
        
        // Build messages array
        $messages = array();
        
        // Add system message with website context
        $messages[] = array(
            'role' => 'system',
            'content' => $website_context
        );
        
        // Add conversation history if available
        if (!empty($conversation_history)) {
            foreach ($conversation_history as $history_message) {
                $messages[] = array(
                    'role' => $history_message['role'] === 'user' ? 'user' : 'assistant',
                    'content' => $history_message['content']
                );
            }
        }
        
        // Add current user message
        $messages[] = array(
            'role' => 'user',
            'content' => $message
        );
        
        $data = array(
            'model' => $selected_model,
            'messages' => $messages,
            'max_tokens' => 1024,
            'temperature' => 0.7
        );
        
        $args = array(
            'body' => json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key
            ),
            'timeout' => 30,
            'user-agent' => 'WordPress/' . get_bloginfo('version') . ' Baachal/1.0.0'
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => 'Network error: ' . $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code === 200) {
            $decoded = json_decode($body, true);
            
            if (isset($decoded['choices'][0]['message']['content'])) {
                return array(
                    'success' => true,
                    'data' => $decoded['choices'][0]['message']['content']
                );
            }
        }
        
        $error_data = json_decode($body, true);
        $error_message = 'Grok API error (HTTP ' . $status_code . ')';
        
        if (isset($error_data['error']['message'])) {
            $error_message .= ': ' . $error_data['error']['message'];
        }
        
        return array(
            'success' => false,
            'error' => $error_message
        );
    }
    
    private function get_website_context($user_message = '') {
        $site_name = get_bloginfo('name');
        $site_description = get_bloginfo('description');
        $site_url = get_home_url();
        
        $context = "You are Baachal, a helpful assistant for the website '{$site_name}'. ";
        $context .= "Website description: {$site_description}. ";
        $context .= "Website URL: {$site_url}. ";
        
        // Add WooCommerce product information if available and enabled
        if (class_exists('WooCommerce') && get_option('baachal_woocommerce_integration', '1') === '1') {
            $woocommerce_context = $this->get_woocommerce_context($user_message);
            if (!empty($woocommerce_context)) {
                $context .= $woocommerce_context;
            }
        }
        
        // Add website content context if enabled
        if (get_option('baachal_content_indexing_enabled', '1') === '1') {
            $content_context = $this->get_content_context($user_message);
            if (!empty($content_context)) {
                $context .= $content_context;
            }
        }
        
        $context .= "Please answer questions about this website helpfully and accurately. ";
        $context .= "When mentioning specific products, always include clickable links in this format: [Product Name](URL) or an HTML link such as <a href=\"URL\">Product Name</a>. ";
        $context .= "When recommending multiple products, let the visitor know that product cards appear beneath your reply. ";
        $context .= "When referencing website pages or content, include clickable links in this format: [Page Title](URL). ";
        $context .= "If you don't know something specific about the website, be honest about it. ";
        $context .= "Keep your responses friendly and concise.";
        
        return $context;
    }
    
    private function get_woocommerce_context($user_message = '') {
        if (!class_exists('WooCommerce')) {
            return '';
        }
        
        // Debug logging if enabled
        $debug_mode = get_option('baachal_debug_mode', '0') === '1';
        
        // If we have a user message, get relevant products using embeddings
        if (!empty($user_message)) {
            if ($debug_mode && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Baachal: Searching for products with message: ' . $user_message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
            
            $relevant_products = $this->get_relevant_products($user_message);
            if (!empty($relevant_products)) {
                if ($debug_mode && defined('WP_DEBUG') && WP_DEBUG) {
                    $product_titles = array_map(function($p) { return $p->post_title; }, $relevant_products);
                    error_log('Baachal: Found products: ' . implode(', ', $product_titles)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                }
                return $this->format_products_context($relevant_products);
            } else {
                if ($debug_mode && defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Baachal: No relevant products found for: ' . $user_message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                }
            }
        }
        
        // Fallback: Get a small sample of recent products
        return $this->get_sample_products_context();
    }
    
    private function get_relevant_products($user_message, $limit = 5) {
        // Create a cache key based on the user message
        $cache_key = 'baachal_products_' . md5(strtolower($user_message)) . '_' . $limit;
        
        // Try to get from cache first (valid for 1 hour)
        $cached_products = get_transient($cache_key);
        if ($cached_products !== false) {
            return $cached_products;
        }
        
        // First, try to find products using WordPress search and keywords
        $products = $this->search_products_by_keywords($user_message, $limit);
        
        if (count($products) < $limit) {
            // If we don't have enough products, add some popular/recent ones
            $additional = $this->get_popular_products($limit - count($products));
            $products = array_merge($products, $additional);
        }
        
        $final_products = array_slice($products, 0, $limit);
        
        // Cache the results for 1 hour
        set_transient($cache_key, $final_products, HOUR_IN_SECONDS);
        
        return $final_products;
    }
    
    private function search_products_by_keywords($user_message, $limit = 5) {
        // Extract keywords from user message - improved version
        $keywords = $this->extract_keywords_improved($user_message);
        
        if (empty($keywords)) {
            return array();
        }
        
        // Multi-stage search for better accuracy
        $products = array();
        
        // Stage 1: Exact phrase search in titles
        $exact_products = $this->search_by_exact_phrase($user_message, $limit);
        $products = array_merge($products, $exact_products);
        
        // Stage 2: Search by individual keywords with weighted scoring
        if (count($products) < $limit) {
            $keyword_products = $this->search_by_weighted_keywords($keywords, $limit - count($products));
            $products = array_merge($products, $keyword_products);
        }
        
        // Stage 3: Search product attributes and variations
        if (count($products) < $limit) {
            $attribute_products = $this->search_by_attributes($keywords, $limit - count($products));
            $products = array_merge($products, $attribute_products);
        }
        
        // Stage 4: Search categories and tags
        if (count($products) < $limit) {
            $taxonomy_products = $this->search_by_taxonomies($keywords, $limit - count($products));
            $products = array_merge($products, $taxonomy_products);
        }
        
        // Remove duplicates and return
        $unique_products = array();
        $seen_ids = array();
        
        foreach ($products as $product) {
            if (!in_array($product->ID, $seen_ids)) {
                $seen_ids[] = $product->ID;
                $unique_products[] = $product;
            }
        }
        
        return array_slice($unique_products, 0, $limit);
    }
    
    private function extract_keywords_improved($message) {
        // Convert to lowercase but preserve important product terms
        $message = strtolower($message);
        
        // Get dynamic important terms from the store's products
        $important_terms = $this->get_dynamic_product_terms();
        
        // Extract multi-word important terms first
        $extracted_terms = array();
        foreach ($important_terms as $term) {
            if (strpos($message, $term) !== false) {
                $extracted_terms[] = $term;
                // Replace with placeholder to avoid double processing
                $message = str_replace($term, ' __TERM__ ', $message);
            }
        }
        
        // Clean up remaining text and split into words
        $message = preg_replace('/[^a-z0-9\s]/', ' ', $message);
        $words = explode(' ', $message);
        
        // Minimal stop words - keep product-related terms
        $stop_words = array(
            'i', 'me', 'my', 'we', 'our', 'you', 'your', 'he', 'him', 'his', 'she', 'her',
            'it', 'its', 'they', 'them', 'their', 'this', 'that', 'these', 'those',
            'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had',
            'do', 'does', 'did', 'a', 'an', 'the', 'and', 'but', 'if', 'or', 'as',
            'of', 'at', 'by', 'for', 'with', 'in', 'on', 'to', 'from',
            'here', 'there', 'when', 'where', 'how', 'all', 'any', 'some', 'no', 'not',
            'can', 'will', 'just', 'should', 'now', '__term__'
        );
        
        // Filter words
        $keywords = array();
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) > 2 && !in_array($word, $stop_words)) {
                $keywords[] = $word;
            }
        }
        
        // Combine extracted terms with individual keywords
        return array_unique(array_merge($extracted_terms, $keywords));
    }
    
    private function get_dynamic_product_terms() {
        // Cache key for product terms
        $cache_key = 'baachal_dynamic_terms';
        
        // Try to get from cache first
        $cached_terms = get_transient($cache_key);
        if ($cached_terms !== false) {
            return $cached_terms;
        }
        
        $important_terms = array();
        
        // Get configurable settings with defaults
        $max_terms = get_option('baachal_max_terms', 50);
        $min_term_length = get_option('baachal_min_term_length', 3);
        $cache_duration = get_option('baachal_cache_duration', DAY_IN_SECONDS);
        $exclude_terms = get_option('baachal_exclude_terms', array('the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'size', 'color', 'item', 'product'));
        
        // Ensure exclude_terms is an array
        if (!is_array($exclude_terms)) {
            $exclude_terms = explode(',', $exclude_terms);
            $exclude_terms = array_map('trim', $exclude_terms);
        }
        
        // Get common terms from product categories
        $category_terms = $this->extract_category_terms($exclude_terms, $min_term_length);
        $important_terms = array_merge($important_terms, $category_terms);
        
        // Get common terms from product attributes
        $attribute_terms = $this->extract_attribute_terms($exclude_terms, $min_term_length);
        $important_terms = array_merge($important_terms, $attribute_terms);
        
        // Get common terms from product tags
        $tag_terms = $this->extract_tag_terms($exclude_terms, $min_term_length);
        $important_terms = array_merge($important_terms, $tag_terms);
        
        // Get common multi-word phrases from product titles
        $title_phrases = $this->extract_title_phrases($exclude_terms, $min_term_length);
        $important_terms = array_merge($important_terms, $title_phrases);
        
        // Remove duplicates and sort by length (longer terms first)
        $important_terms = array_unique($important_terms);
        usort($important_terms, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        
        // Limit to max terms
        if (count($important_terms) > $max_terms) {
            $important_terms = array_slice($important_terms, 0, $max_terms);
        }
        
        // Allow store owners to add custom terms via filter
        $important_terms = apply_filters('baachal_important_product_terms', $important_terms);
        
        // Cache for configured duration
        set_transient($cache_key, $important_terms, $cache_duration);
        
        return $important_terms;
    }
    
    private function extract_category_terms($exclude_terms = array(), $min_term_length = 3) {
        $terms = array();
        
        // Get all product categories
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'number' => 50 // Limit to prevent too many terms
        ));
        
        foreach ($categories as $category) {
            $name = strtolower($category->name);
            if (strlen($name) >= $min_term_length && !in_array($name, $exclude_terms)) {
                $terms[] = $name;
                
                // Also add individual words from multi-word categories
                $words = explode(' ', $name);
                foreach ($words as $word) {
                    $word = trim($word);
                    if (strlen($word) >= $min_term_length && !in_array($word, $exclude_terms)) {
                        $terms[] = $word;
                    }
                }
            }
        }
        
        return $terms;
    }
    
    private function extract_attribute_terms($exclude_terms = array(), $min_term_length = 3) {
        global $wpdb;
        
        $terms = array();
        
        // Get all product attributes
        $attributes = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT meta_key, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE %s
            AND meta_value != %s
            LIMIT %d
        ", 'attribute_%', '', 200));
        
        foreach ($attributes as $attr) {
            // Clean attribute name
            $attr_name = str_replace('attribute_pa_', '', $attr->meta_key);
            $attr_name = str_replace('attribute_', '', $attr_name);
            $attr_name = str_replace('-', ' ', $attr_name);
            $attr_name = str_replace('_', ' ', $attr_name);
            
            if (strlen($attr_name) >= $min_term_length && !in_array(strtolower($attr_name), $exclude_terms)) {
                $terms[] = strtolower($attr_name);
            }
            
            // Clean attribute value
            $attr_value = str_replace('-', ' ', $attr->meta_value);
            $attr_value = str_replace('_', ' ', $attr_value);
            
            if (strlen($attr_value) >= $min_term_length && !in_array(strtolower($attr_value), $exclude_terms)) {
                $terms[] = strtolower($attr_value);
            }
        }
        
        return $terms;
    }
    
    private function extract_tag_terms($exclude_terms = array(), $min_term_length = 3) {
        $terms = array();
        
        // Get all product tags
        $tags = get_terms(array(
            'taxonomy' => 'product_tag',
            'hide_empty' => true,
            'number' => 50
        ));
        
        foreach ($tags as $tag) {
            $name = strtolower($tag->name);
            if (strlen($name) >= $min_term_length && !in_array($name, $exclude_terms)) {
                $terms[] = $name;
            }
        }
        
        return $terms;
    }
    
    private function extract_title_phrases($exclude_terms = array(), $min_term_length = 3) {
        global $wpdb;
        
        $terms = array();
        
        // Get common phrases from product titles
        $titles = $wpdb->get_col("
            SELECT post_title 
            FROM {$wpdb->posts} 
            WHERE post_type = 'product' 
            AND post_status = 'publish'
            ORDER BY post_date DESC
            LIMIT 100
        ");
        
        $phrase_counts = array();
        
        foreach ($titles as $title) {
            $title = strtolower($title);
            // Extract 2-3 word phrases
            $words = explode(' ', $title);
            
            for ($i = 0; $i < count($words) - 1; $i++) {
                // 2-word phrases
                if (isset($words[$i + 1])) {
                    $phrase = trim($words[$i] . ' ' . $words[$i + 1]);
                    if (strlen($phrase) >= $min_term_length * 2 && !$this->is_common_phrase($phrase, $exclude_terms)) {
                        $phrase_counts[$phrase] = isset($phrase_counts[$phrase]) ? $phrase_counts[$phrase] + 1 : 1;
                    }
                }
                
                // 3-word phrases
                if (isset($words[$i + 2])) {
                    $phrase = trim($words[$i] . ' ' . $words[$i + 1] . ' ' . $words[$i + 2]);
                    if (strlen($phrase) >= $min_term_length * 3 && !$this->is_common_phrase($phrase, $exclude_terms)) {
                        $phrase_counts[$phrase] = isset($phrase_counts[$phrase]) ? $phrase_counts[$phrase] + 1 : 1;
                    }
                }
            }
        }
        
        // Keep phrases that appear at least twice
        foreach ($phrase_counts as $phrase => $count) {
            if ($count >= 2) {
                $terms[] = $phrase;
            }
        }
        
        return $terms;
    }
    
    private function is_common_phrase($phrase, $exclude_terms = array()) {
        $common_phrases = array(
            'on sale', 'for sale', 'in stock', 'out of', 'the best', 'and more',
            'with free', 'free shipping', 'buy now', 'add to', 'view all'
        );
        
        // Merge with custom exclude terms
        $all_excludes = array_merge($common_phrases, $exclude_terms);
        
        return in_array($phrase, $all_excludes);
    }
    
    private function search_by_exact_phrase($phrase, $limit) {
        // Clean the phrase but keep it as a whole
        $clean_phrase = preg_replace('/[^a-zA-Z0-9\s-]/', '', $phrase);
        
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            's' => '"' . $clean_phrase . '"', // Exact phrase search
            'meta_query' => array(
                array(
                    'key' => '_visibility',
                    'value' => array('catalog', 'visible'),
                    'compare' => 'IN'
                )
            )
        );
        
        return get_posts($args);
    }
    
    private function search_by_weighted_keywords($keywords, $limit) {
        global $wpdb;
        
        if (empty($keywords)) {
            return array();
        }
        
        // Create placeholders and collect values separately for title and content
        $title_conditions = array();
        $content_conditions = array();
        $title_values = array();
        $content_values = array();
        
        foreach ($keywords as $keyword) {
            $like_keyword = '%' . $wpdb->esc_like($keyword) . '%';
            $title_conditions[] = "p.post_title LIKE %s";
            $content_conditions[] = "p.post_content LIKE %s";
            $title_values[] = $like_keyword;
            $content_values[] = $like_keyword;
        }
        
        $title_sql = implode(' OR ', $title_conditions);
        $content_sql = implode(' OR ', $content_conditions);
        
        // Build the complete SQL with placeholders
        $sql = "
            SELECT p.ID, p.post_title,
                   (CASE WHEN ({$title_sql}) THEN 2 ELSE 0 END) +
                   (CASE WHEN ({$content_sql}) THEN 1 ELSE 0 END) as relevance_score
            FROM {$wpdb->posts} p
            WHERE p.post_type = %s
            AND p.post_status = %s
            AND (({$title_sql}) OR ({$content_sql}))
            ORDER BY relevance_score DESC, p.post_date DESC
            LIMIT %d
        ";
        
        // Prepare all values in correct order: title values, content values, post_type, post_status, title values again, content values again, limit
        $prepare_values = array_merge(
            $title_values,   // First set for CASE WHEN title
            $content_values, // Second set for CASE WHEN content  
            array('product'), // post_type
            array('publish'), // post_status
            $title_values,   // Third set for WHERE title
            $content_values, // Fourth set for WHERE content
            array($limit)    // limit
        );
        
        $prepared_sql = $wpdb->prepare($sql, $prepare_values);
        $results = $wpdb->get_results($prepared_sql);
        $products = array();
        
        foreach ($results as $result) {
            $product = get_post($result->ID);
            if ($product) {
                $products[] = $product;
            }
        }
        
        return $products;
    }
    
    private function search_by_attributes($keywords, $limit) {
        global $wpdb;
        
        if (empty($keywords)) {
            return array();
        }
        
        // Search in product attributes (like color, material, etc.)
        $attribute_conditions = array();
        $condition_values = array();
        
        foreach ($keywords as $keyword) {
            $like_keyword = '%' . $wpdb->esc_like($keyword) . '%';
            $attribute_conditions[] = "pm.meta_value LIKE %s";
            $condition_values[] = $like_keyword;
        }
        
        if (empty($attribute_conditions)) {
            return array();
        }
        
        $conditions_sql = implode(' OR ', $attribute_conditions);
        
        // Prepare the complete SQL with all placeholders
        $sql = "
            SELECT DISTINCT p.ID, p.post_title
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type IN (%s, %s)
            AND p.post_status = %s
            AND pm.meta_key LIKE %s
            AND ({$conditions_sql})
            ORDER BY p.post_date DESC
            LIMIT %d
        ";
        
        // Prepare all values in one call
        $prepare_values = array_merge(
            array('product', 'product_variation', 'publish', 'attribute_%'),
            $condition_values,
            array($limit)
        );
        
        $prepared_sql = $wpdb->prepare($sql, $prepare_values);
        $results = $wpdb->get_results($prepared_sql);
        $products = array();
        
        foreach ($results as $result) {
            // If it's a variation, get the parent product
            $product_id = $result->ID;
            $product = get_post($product_id);
            
            if ($product && $product->post_type === 'product_variation') {
                $parent_id = wp_get_post_parent_id($product_id);
                if ($parent_id) {
                    $product = get_post($parent_id);
                }
            }
            
            if ($product && $product->post_type === 'product') {
                $products[] = $product;
            }
        }
        
        return $products;
    }
    
    private function search_by_taxonomies($keywords, $limit) {
        if (empty($keywords)) {
            return array();
        }
        
        // Search in product categories and tags
        $tax_query = array('relation' => 'OR');
        
        foreach ($keywords as $keyword) {
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field' => 'name',
                'terms' => $keyword,
                'operator' => 'LIKE'
            );
            $tax_query[] = array(
                'taxonomy' => 'product_tag',
                'field' => 'name', 
                'terms' => $keyword,
                'operator' => 'LIKE'
            );
        }
        
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'tax_query' => $tax_query,
            'meta_query' => array(
                array(
                    'key' => '_visibility',
                    'value' => array('catalog', 'visible'),
                    'compare' => 'IN'
                )
            )
        );
        
        return get_posts($args);
    }
    
    private function get_popular_products($limit = 5) {
        // Get best-selling or most viewed products
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_visibility',
                    'value' => array('catalog', 'visible'),
                    'compare' => 'IN'
                )
            ),
            'meta_key' => 'total_sales',
            'orderby' => 'meta_value_num',
            'order' => 'DESC'
        );
        
        $products = get_posts($args);
        
        // If no sales data, get recent products
        if (empty($products)) {
            $args['meta_key'] = '';
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            $products = get_posts($args);
        }
        
        return $products;
    }
    
    private function get_sample_products_context() {
        // Get just a small sample of products for general context
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 8, // Much smaller sample
            'post_status' => 'publish',
            'orderby' => 'rand', // Random sample
            'meta_query' => array(
                array(
                    'key' => '_visibility',
                    'value' => array('catalog', 'visible'),
                    'compare' => 'IN'
                )
            )
        );
        
        $products = get_posts($args);
        return $this->format_products_context($products, false);
    }
    
    private function format_products_context($products, $render_cards = true) {
        if (empty($products)) {
            $this->current_product_cards_html = '';
            return '';
        }
        
        $cards = array();
        
        foreach ($products as $product) {
            $wc_product = wc_get_product($product->ID);
            if (!$wc_product || !$wc_product->is_visible()) {
                continue;
            }
            
            $price_html = $wc_product->get_price_html();
            $price_text = $price_html ? wp_strip_all_tags($price_html) : '';
            $raw_price = $wc_product->get_price();
            if (empty($price_text) && $raw_price !== '') {
                if (function_exists('wc_price')) {
                    $price_text = wp_strip_all_tags(wc_price($raw_price));
                } else {
                    $price_text = $raw_price;
                }
            }
            
            $categories = wp_get_post_terms($product->ID, 'product_cat', array('fields' => 'names'));
            $category_names = !empty($categories) ? implode(', ', $categories) : __('Uncategorized', 'baachal');
            
            $attributes_raw = $this->get_product_attributes($wc_product);
            $attributes = array();
            foreach ($attributes_raw as $attribute) {
                $attributes[] = wp_strip_all_tags($attribute);
            }
            
            $stock_status = $wc_product->get_stock_status();
            $stock_message = '';
            if ($stock_status === 'outofstock') {
                $stock_message = __('Out of stock', 'baachal');
            } elseif ($stock_status === 'onbackorder') {
                $stock_message = __('Available on backorder', 'baachal');
            } elseif ($stock_status === 'instock') {
                if ($wc_product->managing_stock()) {
                    $quantity = $wc_product->get_stock_quantity();
                    if (!is_null($quantity)) {
                        $stock_message = sprintf(
                            _n('%d item available', '%d items available', $quantity, 'baachal'),
                            $quantity
                        );
                    } else {
                        $stock_message = __('In stock', 'baachal');
                    }
                } else {
                    $stock_message = __('In stock', 'baachal');
                }
            }
            
            $description = '';
            if ($wc_product->get_short_description()) {
                $description = wp_strip_all_tags($wc_product->get_short_description());
                $description = wp_trim_words($description, 30, '...');
            }
            
            $image_url = get_the_post_thumbnail_url($product->ID, 'woocommerce_thumbnail');
            if (!$image_url) {
                $image_url = '';
            }
            
            $cards[] = array(
                'id' => $product->ID,
                'name' => $product->post_title,
                'url' => get_permalink($product->ID),
                'image' => $image_url,
                'price_html' => $price_html,
                'price_text' => $price_text,
                'categories' => $category_names,
                'attributes' => $attributes,
                'stock_status' => $stock_status,
                'stock_message' => $stock_message,
                'description' => $description,
            );
        }
        
        if (empty($cards)) {
            $this->current_product_cards_html = '';
            return '';
        }
        
        $cards = apply_filters('baachal_product_card_data', $cards, $products);
        
        if ($render_cards) {
            $this->current_product_cards_html = $this->build_product_cards_html($cards);
        } else {
            $this->current_product_cards_html = '';
        }
        
        $context = "\n\nThis is a WooCommerce online store. Here are the most relevant products matching your query:\n";
        
        foreach ($cards as $card) {
            $product_info = "- **{$card['name']}**";
            
            if (!empty($card['price_text'])) {
                $product_info .= " | Price: {$card['price_text']}";
            }
            
            if (!empty($card['categories'])) {
                $product_info .= " | Category: {$card['categories']}";
            }
            
            if (!empty($card['attributes'])) {
                $attributes_list = is_array($card['attributes']) ? implode(', ', $card['attributes']) : $card['attributes'];
                $product_info .= " | {$attributes_list}";
            }
            
            $product_info .= " | URL: {$card['url']}";
            
            if (!empty($card['description'])) {
                $product_info .= " | Description: {$card['description']}";
            }
            
            if (!empty($card['stock_message'])) {
                $product_info .= " | Stock: {$card['stock_message']}";
            }
            
            $context .= $product_info . "\n";
        }
        
        $context .= "\nInstructions for product recommendations:\n";
        $context .= "- Reference the product names exactly as provided so they align with the product cards appended to your reply\n";
        $context .= "- Keep your introduction concise (1-2 sentences) before highlighting the products\n";
        $context .= "- Encourage the customer to open the product links or browse related categories for similar options\n";
        $context .= "- Focus on attributes (material, color, size, etc.) that match the user's intent\n";
        $context .= "- A formatted product card grid will automatically appear beneath your message using the same data—do not repeat full specifications\n";
        
        return $context;
    }
    
    private function build_product_cards_html($cards) {
        if (empty($cards)) {
            return '';
        }
        
        $cards_html = '<div class="baachal-product-grid">';
        
        foreach ($cards as $card) {
            $url = isset($card['url']) ? $card['url'] : '';
            $name = isset($card['name']) ? $card['name'] : '';
            $image = isset($card['image']) ? $card['image'] : '';
            $price_html = isset($card['price_html']) ? $card['price_html'] : '';
            $price_text = isset($card['price_text']) ? $card['price_text'] : '';
            
            $cards_html .= '<article class="baachal-product-card">';
            
            $link_tag = 'div';
            $link_classes = 'baachal-product-link baachal-product-link--static';
            $link_attrs = '';
            
            if (!empty($url)) {
                $link_tag = 'a';
                $link_classes = 'baachal-product-link';
                $link_attrs = ' href="' . esc_url($url) . '" target="_blank" rel="noopener"';
            }
            
            $cards_html .= '<' . $link_tag . ' class="' . esc_attr($link_classes) . '"' . $link_attrs . '>';
            
            if (!empty($image)) {
                $cards_html .= '<div class="baachal-product-image">';
                $cards_html .= '<img src="' . esc_url($image) . '" alt="' . esc_attr($name) . '" loading="lazy" />';
                $cards_html .= '</div>';
            } else {
                $placeholder_label = !empty($url) ? esc_html__('View product', 'baachal') : esc_html__('No image available', 'baachal');
                $cards_html .= '<div class="baachal-product-image baachal-product-image--placeholder"><span>' . $placeholder_label . '</span></div>';
            }
            
            $cards_html .= '<div class="baachal-product-body">';
            $cards_html .= '<h4 class="baachal-product-title">' . esc_html($name) . '</h4>';
            
            if (!empty($price_html)) {
                $cards_html .= '<div class="baachal-product-price">' . wp_kses_post($price_html) . '</div>';
            } elseif (!empty($price_text)) {
                $cards_html .= '<div class="baachal-product-price">' . esc_html($price_text) . '</div>';
            }
            
            $cards_html .= '</div>';
            $cards_html .= '</' . $link_tag . '>';
            $cards_html .= '</article>';
        }
        
        $cards_html .= '</div>';
        
        return apply_filters('baachal_product_cards_html', $cards_html, $cards);
    }
    
    private function get_content_context($user_message = '') {
        // Debug logging if enabled
        $debug_mode = get_option('baachal_debug_mode', '0') === '1';
        
        if (empty($user_message)) {
            return '';
        }
        
        // Get content indexer instance
        $indexer = new Baachal_Content_Indexer();
        
        // Get max results from settings
        $max_results = get_option('baachal_content_max_results', 5);
        
        // Search for relevant content
        $relevant_content = $indexer->search_content($user_message, $max_results);
        
        if (empty($relevant_content)) {
            if ($debug_mode && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Baachal: No relevant content found for: ' . $user_message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
            return '';
        }
        
        if ($debug_mode && defined('WP_DEBUG') && WP_DEBUG) {
            $content_titles = array_map(function($c) { return $c['title']; }, $relevant_content);
            error_log('Baachal: Found content: ' . implode(', ', $content_titles)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
        
        return $this->format_content_context($relevant_content);
    }
    
    private function format_content_context($content_items) {
        if (empty($content_items)) {
            return '';
        }
        
        $context = "\n\nHere is relevant content from this website that may help answer your question:\n";
        
        foreach ($content_items as $item) {
            $context .= "- **{$item['title']}** ({$item['post_type']})";
            
            if (!empty($item['excerpt'])) {
                $excerpt = wp_strip_all_tags($item['excerpt']);
                $excerpt = substr($excerpt, 0, 150) . (strlen($excerpt) > 150 ? '...' : '');
                $context .= " | {$excerpt}";
            }
            
            $context .= " | URL: {$item['url']}\n";
        }
        
        $context .= "\nInstructions for using website content:\n";
        $context .= "- Use this content to provide accurate answers about the website\n";
        $context .= "- ALWAYS include page URLs as clickable links: [Page Title](URL)\n";
        $context .= "- If the content doesn't fully answer the question, be honest about limitations\n";
        $context .= "- Encourage users to visit the linked pages for more detailed information\n";
        $context .= "- Synthesize information from multiple sources when relevant\n";
        
        return $context;
    }
    
    private function get_product_attributes($wc_product) {
        $attributes = array();
        
        if (!$wc_product) {
            return $attributes;
        }
        
        // Get product attributes
        $product_attributes = $wc_product->get_attributes();
        
        foreach ($product_attributes as $attribute) {
            if ($attribute->get_visible()) {
                $attribute_name = $attribute->get_name();
                $attribute_label = wc_attribute_label($attribute_name);
                
                if ($attribute->is_taxonomy()) {
                    $terms = wp_get_post_terms($wc_product->get_id(), $attribute_name, array('fields' => 'names'));
                    if (!empty($terms)) {
                        $attributes[] = $attribute_label . ': ' . implode(', ', $terms);
                    }
                } else {
                    $values = $attribute->get_options();
                    if (!empty($values)) {
                        $attributes[] = $attribute_label . ': ' . implode(', ', $values);
                    }
                }
            }
        }
        
        return $attributes;
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Baachal AI bot Settings',
            'Baachal AI bot',
            'manage_options',
            'baachal',
            array($this, 'admin_page')
        );
    }
    
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=baachal') . '">' . __('Settings', 'baachal') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    public function add_chat_meta_boxes() {
        add_meta_box(
            'chatbot-conversation-details',
            'Conversation Details',
            array($this, 'render_conversation_meta_box'),
            'baachal_conversation',
            'normal',
            'high'
        );
        
        add_meta_box(
            'chatbot-conversation-messages',
            'Chat Messages',
            array($this, 'render_messages_meta_box'),
            'baachal_conversation',
            'normal',
            'high'
        );
        
        // Add content indexing meta box to indexable post types
        $indexable_types = get_option('baachal_indexable_post_types', array('post', 'page'));
        foreach ($indexable_types as $post_type) {
            add_meta_box(
                'baachal-content-indexing',
                'Baachal AI Bot - Content Indexing',
                array($this, 'render_content_indexing_meta_box'),
                $post_type,
                'side',
                'default'
            );
        }
    }
    
    public function render_conversation_meta_box($post) {
        $session_id = get_post_meta($post->ID, '_session_id', true);
        $user_id = get_post_meta($post->ID, '_user_id', true);
        $user_ip = get_post_meta($post->ID, '_user_ip', true);
        $user_agent = get_post_meta($post->ID, '_user_agent', true);
        
        echo '<table class="form-table">';
        echo '<tr><th>Session ID:</th><td>' . esc_html($session_id) . '</td></tr>';
        echo '<tr><th>User ID:</th><td>' . ($user_id ? esc_html($user_id) : 'Anonymous') . '</td></tr>';
        echo '<tr><th>IP Address:</th><td>' . esc_html($user_ip) . '</td></tr>';
        echo '<tr><th>User Agent:</th><td>' . esc_html($user_agent) . '</td></tr>';
        echo '<tr><th>Created:</th><td>' . get_the_date('Y-m-d H:i:s', $post->ID) . '</td></tr>';
        echo '</table>';
    }
    
    public function render_messages_meta_box($post) {
        $messages = get_post_meta($post->ID, '_chat_messages', true);
        
        if (empty($messages) || !is_array($messages)) {
            echo '<p>No messages in this conversation yet.</p>';
            return;
        }
        
        echo '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">';
        
        foreach ($messages as $index => $message) {
            $type = $message['type'];
            $content = $message['message'];
            $timestamp = $message['timestamp'];
            $user_id = isset($message['user_id']) ? $message['user_id'] : '';
            
            $bg_color = ($type === 'user') ? '#e3f2fd' : '#f3e5f5';
            $label = ($type === 'user') ? 'User' : 'Baachal AI bot';
            
            echo '<div style="margin-bottom: 15px; padding: 10px; background: ' . esc_attr($bg_color) . '; border-radius: 5px;">';
            echo '<div style="font-weight: bold; margin-bottom: 5px; color: #333;">';
            echo esc_html($label) . ' <span style="font-weight: normal; color: #666; font-size: 12px;">(' . esc_html($timestamp) . ')</span>';
            echo '</div>';
            echo '<div style="line-height: 1.4;">' . nl2br(esc_html($content)) . '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        
        echo '<p style="margin-top: 10px;"><strong>Total Messages:</strong> ' . count($messages) . '</p>';
    }
    
    public function render_content_indexing_meta_box($post) {
        wp_nonce_field('baachal_content_indexing', 'baachal_content_indexing_nonce');
        
        $exclude_from_index = get_post_meta($post->ID, '_baachal_exclude_from_index', true);
        
        echo '<p>';
        echo '<label>';
        echo '<input type="checkbox" name="baachal_exclude_from_index" value="1" ' . checked($exclude_from_index, '1', false) . ' />';
        echo ' Exclude from chatbot indexing';
        echo '</label>';
        echo '</p>';
        echo '<p class="description">Check this box to prevent this content from being indexed for chatbot searches.</p>';
        
        // Add save hook
        add_action('save_post', array($this, 'save_content_indexing_meta'));
    }
    
    public function save_content_indexing_meta($post_id) {
        // Check if our nonce is set and verify it
        if (!isset($_POST['baachal_content_indexing_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['baachal_content_indexing_nonce'])), 'baachal_content_indexing')) {
            return;
        }
        
        // If this is an autosave, our form has not been submitted, so we don't want to do anything
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check the user's permissions
        if (isset($_POST['post_type']) && 'page' == $_POST['post_type']) {
            if (!current_user_can('edit_page', $post_id)) {
                return;
            }
        } else {
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
        }
        
        // Save the meta value
        if (isset($_POST['baachal_exclude_from_index'])) {
            update_post_meta($post_id, '_baachal_exclude_from_index', '1');
        } else {
            delete_post_meta($post_id, '_baachal_exclude_from_index');
        }
    }
    
    public function register_settings() {
        register_setting('baachal_settings', 'baachal_gemini_api_key', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('baachal_settings', 'baachal_enabled', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        register_setting('baachal_settings', 'baachal_welcome_message', array(
            'sanitize_callback' => 'sanitize_textarea_field'
        ));
        register_setting('baachal_settings', 'baachal_debug_mode', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        register_setting('baachal_settings', 'baachal_gemini_model', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('baachal_settings', 'baachal_woocommerce_integration', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        register_setting('baachal_settings', 'baachal_message_persistence', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        register_setting('baachal_settings', 'baachal_show_clear_history', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        
        // Product search settings
        register_setting('baachal_settings', 'baachal_max_terms', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('baachal_settings', 'baachal_min_term_length', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('baachal_settings', 'baachal_cache_duration', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('baachal_settings', 'baachal_exclude_terms', array(
            'sanitize_callback' => 'sanitize_textarea_field'
        ));
        
        // UI styling settings
        register_setting('baachal_settings', 'baachal_primary_color', array(
            'sanitize_callback' => 'sanitize_hex_color'
        ));
        register_setting('baachal_settings', 'baachal_secondary_color', array(
            'sanitize_callback' => 'sanitize_hex_color'
        ));
        register_setting('baachal_settings', 'baachal_position', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('baachal_settings', 'baachal_size', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('baachal_settings', 'baachal_border_radius', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('baachal_settings', 'baachal_chat_height', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('baachal_settings', 'baachal_font_size', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('baachal_settings', 'baachal_animation_enabled', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        register_setting('baachal_settings', 'baachal_conversation_memory', array(
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
        register_setting('baachal_settings', 'baachal_memory_limit', array(
            'sanitize_callback' => 'absint'
        ));
    }
    
    /**
     * Sanitize checkbox input
     */
    public function sanitize_checkbox($input) {
        return ($input === '1' || $input === 1 || $input === true) ? '1' : '0';
    }
    
    public function admin_page() {
        include BAACHAL_PLUGIN_PATH . 'admin/settings-page.php';
    }
}

// Initialize the plugin
new Baachal();
