<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Enhanced meta box to hide chatbot and select specific bot on pages
 */
class MxChat_Meta_Box {
    
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_chatbot_meta_box'));
        add_action('save_post', array($this, 'save_chatbot_meta_box'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_gutenberg_assets'));
        add_action('init', array($this, 'register_meta_fields'));
    }
    
    /**
     * Add meta box to posts and pages
     */
    public function add_chatbot_meta_box() {
        $post_types = get_post_types(array('public' => true), 'names');
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'mxchat_visibility',
                __('MxChat Settings', 'mxchat'),
                array($this, 'render_meta_box'),
                $post_type,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Register meta fields for Gutenberg
     */
    public function register_meta_fields() {
        register_post_meta('', '_mxchat_hide_chatbot', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        register_post_meta('', '_mxchat_selected_bot', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'default' => '',
            'auth_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
    }
    
    /**
     * Get available bots
     */
    private function get_available_bots() {
        $bots = array();
        
        // Check if multi-bot addon is active
        if (class_exists('MxChat_Multi_Bot_Manager')) {
            $multi_bot_manager = MxChat_Multi_Bot_Core_Manager::get_instance();
            $available_bots = $multi_bot_manager->get_available_bots();
            
            // Add the available bots
            foreach ($available_bots as $bot_id => $bot_name) {
                $bots[$bot_id] = $bot_name;
            }
        } else {
            // Only default bot available
            $bots['default'] = __('Default Bot', 'mxchat');
        }
        
        return $bots;
    }
    
    /**
     * Check if global auto-show is enabled
     */
    private function is_global_autoshow_enabled() {
        $options = get_option('mxchat_options', array());
        return isset($options['append_to_body']) && $options['append_to_body'] === 'on';
    }
    
    /**
     * Updated render_meta_box method with clarified copy
     */
    public function render_meta_box($post) {
        wp_nonce_field('mxchat_meta_box_nonce', 'mxchat_meta_box_nonce');
        
        $hide_chatbot = get_post_meta($post->ID, '_mxchat_hide_chatbot', true);
        $selected_bot = get_post_meta($post->ID, '_mxchat_selected_bot', true);
        $available_bots = $this->get_available_bots();
        $global_autoshow = $this->is_global_autoshow_enabled();
        $has_multibot = class_exists('MxChat_Multi_Bot_Manager');
        
        ?>
        <div style="padding: 10px 0;">
            
            <!-- Hide Chatbot Option -->
            <div style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center;">
                    <input type="checkbox" 
                           name="mxchat_hide_chatbot" 
                           value="1" 
                           <?php checked($hide_chatbot, '1'); ?>
                           style="margin-right: 8px;" />
                    <?php _e('Hide auto-floating chatbot on this page', 'mxchat'); ?>
                </label>
                <p style="font-size: 12px; color: #666; margin-top: 5px; font-style: italic;">
                    <?php _e('Prevents the floating chatbot from auto-appearing. Embedded chatbots (floating="no") will still work.', 'mxchat'); ?>
                </p>
            </div>
            
            <!-- Bot Selection (only show if multi-bot is available) -->
            <?php if ($has_multibot && count($available_bots) > 1) : ?>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">
                        <?php _e('Select Bot for this Page:', 'mxchat'); ?>
                    </label>
                    <select name="mxchat_selected_bot" style="width: 100%;">
                        <option value=""><?php _e('Use Global Setting', 'mxchat'); ?></option>
                        <?php foreach ($available_bots as $bot_id => $bot_name) : ?>
                            <option value="<?php echo esc_attr($bot_id); ?>" <?php selected($selected_bot, $bot_id); ?>>
                                <?php echo esc_html($bot_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <div style="margin-top: 8px; padding: 8px; background: #f8f9fa; border-left: 3px solid #007cba; font-size: 12px;">
                        <?php if ($global_autoshow) : ?>
                            <p style="margin: 0 0 5px 0; color: #0073aa;">
                                <strong><?php _e('Global Auto-Show: ON', 'mxchat'); ?></strong>
                            </p>
                            <p style="margin: 0; color: #666;">
                                <?php _e('• "Use Global Setting" = Default bot will show automatically', 'mxchat'); ?><br>
                                <?php _e('• Select specific bot = That bot will show instead', 'mxchat'); ?><br>
                                <?php _e('• Check "Hide auto-floating" above = No auto bot, but shortcodes still work', 'mxchat'); ?>
                            </p>
                        <?php else : ?>
                            <p style="margin: 0 0 5px 0; color: #d63638;">
                                <strong><?php _e('Global Auto-Show: OFF', 'mxchat'); ?></strong>
                            </p>
                            <p style="margin: 0; color: #666;">
                                <?php _e('• "Use Global Setting" = No bot will show (use shortcodes)', 'mxchat'); ?><br>
                                <?php _e('• Select specific bot = That bot will show on this page only', 'mxchat'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Preview Notice -->
            <div style="margin-top: 15px; padding: 8px; background: #fff3cd; border-left: 3px solid #ffc107; font-size: 12px;">
                <p style="margin: 0; color: #856404;">
                    <strong><?php _e('Important:', 'mxchat'); ?></strong> 
                    <?php _e('Bot changes only take effect after saving/updating the page. Preview mode will show the previously saved settings.', 'mxchat'); ?>
                </p>
            </div>
            
            <!-- Shortcode Examples -->
            <div style="margin-top: 10px; padding: 8px; background: #e7f3ff; border-left: 3px solid #2196f3; font-size: 12px;">
                <p style="margin: 0 0 5px 0; color: #1565c0;">
                    <strong><?php _e('Shortcode Examples:', 'mxchat'); ?></strong>
                </p>
                <p style="margin: 0; color: #666; font-family: monospace;">
                    <?php _e('[mxchat_chatbot floating="yes"] - Floating chatbot', 'mxchat'); ?><br>
                    <?php _e('[mxchat_chatbot floating="no"] - Embedded chatbot (always works)', 'mxchat'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Save meta box data
     */
    public function save_chatbot_meta_box($post_id) {
        if (!isset($_POST['mxchat_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['mxchat_meta_box_nonce'], 'mxchat_meta_box_nonce')) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Save hide chatbot setting
        if (isset($_POST['mxchat_hide_chatbot'])) {
            update_post_meta($post_id, '_mxchat_hide_chatbot', '1');
        } else {
            delete_post_meta($post_id, '_mxchat_hide_chatbot');
        }
        
        // Save selected bot setting
        if (isset($_POST['mxchat_selected_bot'])) {
            $selected_bot = sanitize_text_field($_POST['mxchat_selected_bot']);
            if (!empty($selected_bot)) {
                update_post_meta($post_id, '_mxchat_selected_bot', $selected_bot);
            } else {
                delete_post_meta($post_id, '_mxchat_selected_bot');
            }
        }
    }
    
    /**
     * Enqueue Gutenberg assets
     */
    public function enqueue_gutenberg_assets() {
        $available_bots = $this->get_available_bots();
        $global_autoshow = $this->is_global_autoshow_enabled();
        $has_multibot = class_exists('MxChat_Multi_Bot_Manager');
        
        // Enqueue the JavaScript file
        wp_enqueue_script(
            'mxchat-meta-box',
            plugin_dir_url(__FILE__) . 'js/meta-box.js',
            array('wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-plugins'),
            '1.0.0',
            true
        );
        
        // Localize script with data
        wp_localize_script('mxchat-meta-box', 'mxchatMetaBox', array(
            'availableBots' => $available_bots,
            'globalAutoshow' => $global_autoshow,
            'hasMultibot' => $has_multibot,
            'strings' => array(
                'panelTitle' => __('MxChat Settings', 'mxchat'),
                'useGlobalSetting' => __('Use Global Setting', 'mxchat'),
                'hideChatbot' => __('Hide chatbot on this page', 'mxchat'),
                'hideChatbotHelp' => __('Prevent any chatbot from appearing on this page', 'mxchat'),
                'selectBot' => __('Select Bot for this Page', 'mxchat'),
                'globalAutoshowOn' => __('Global Auto-Show: ON. Use Global Setting = Default bot shows automatically. Select specific bot = That bot shows instead.', 'mxchat'),
                'globalAutoshowOff' => __('Global Auto-Show: OFF. Use Global Setting = No bot shows. Select specific bot = That bot shows on this page only.', 'mxchat')
            )
        ));
    }
    
    /**
     * Helper function to get the bot that should display on current page
     * Call this from your main chatbot display logic
     */
    public static function get_page_bot_setting($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        if (!$post_id) {
            return null;
        }
        
        // Check if chatbot is hidden on this page
        $hide_chatbot = get_post_meta($post_id, '_mxchat_hide_chatbot', true);
        if ($hide_chatbot === '1') {
            return array('action' => 'hide');
        }
        
        // Check if specific bot is selected
        $selected_bot = get_post_meta($post_id, '_mxchat_selected_bot', true);
        if (!empty($selected_bot)) {
            return array('action' => 'show', 'bot_id' => $selected_bot);
        }
        
        // Use global setting
        return array('action' => 'global');
    }
}