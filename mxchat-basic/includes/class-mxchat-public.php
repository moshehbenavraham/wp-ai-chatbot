<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MxChat_Public {
    private $options;

    public function __construct() {
        // Simply get the options without defining duplicated defaults
        $this->options = get_option('mxchat_options', array());
        add_shortcode('mxchat_chatbot', array($this, 'render_chatbot_shortcode'));
        add_action('wp_footer', array($this, 'append_chatbot_to_body'));
        
        // Initialize testing panel for admins
        add_action('wp_enqueue_scripts', array($this, 'enqueue_testing_assets'));
        
        // Add debug hook
        add_action('wp_footer', array($this, 'debug_display_logic'));
    }

    /**
     * Enqueue testing panel assets for admin users
     */
    public function enqueue_testing_assets() {
        // Check if frontend debugger is enabled in settings
        $options = get_option('mxchat_options', array());
        $show_debugger = isset($options['show_frontend_debugger']) ? $options['show_frontend_debugger'] : 'on';

        // Only load if setting is enabled AND (user is admin or testing parameter is present)
        if ($show_debugger === 'on' && (current_user_can('administrator') || isset($_GET['mxchat_test']))) {

            // Get plugin URL for assets
            $plugin_url = plugin_dir_url(dirname(__FILE__));
            
            // Enqueue test panel CSS
            wp_enqueue_style(
                'mxchat-test-panel',
                $plugin_url . 'css/test-panel.css',
                array(),
                '2.5.2'
            );
            
            // Enqueue test panel JS
            wp_enqueue_script(
                'mxchat-test-panel',
                $plugin_url . 'js/test-panel.js',
                array('jquery'),
                '2.5.2',
                true
            );
            
            // Pass data to JavaScript
            wp_localize_script('mxchat-test-panel', 'mxchatTestData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mxchat_test_nonce'),
                'isAdmin' => current_user_can('administrator'),
                'testingEnabled' => true
            ));
            
            // Add JavaScript flag to enable testing
            add_action('wp_footer', array($this, 'add_testing_flag'));
        }
    }

    /**
     * Add JavaScript flag to enable testing panel
     */
    public function add_testing_flag() {
        echo '<script>window.mxchatTestingEnabled = true;</script>';
    }

    /**
     * Check if testing mode should be enabled
     */
    private function is_testing_mode_enabled() {
        return (current_user_can('administrator') || isset($_GET['mxchat_test']));
    }

/**
 * UPDATED: Enhanced should_hide_chatbot method - only blocks auto-append, not shortcodes
 */
private function should_hide_chatbot($context = 'auto') {
    global $post;
    
    if (!$post) {
        return false;
    }
    
    $hide_chatbot = get_post_meta($post->ID, '_mxchat_hide_chatbot', true);
    
    // Only hide if it's the auto-append context (floating="yes" from global setting)
    // Allow shortcodes to work regardless of this setting
    if ($context === 'auto' && $hide_chatbot === '1') {
        return true;
    }
    
    return false;
}

/**
 * UPDATED: Enhanced append_chatbot_to_body method with context
 */
public function append_chatbot_to_body() {
    // Only run on public pages
    if (is_admin() || wp_doing_ajax()) {
        return;
    }
    
    // Check if auto-append chatbot should be hidden on this page
    if ($this->should_hide_chatbot('auto')) {
        return; // Don't show auto-appended chatbot
    }
    
    // Get the bot that should be displayed using new logic
    $bot_to_show = $this->get_display_bot();
    
    // Don't show chatbot if determination is false
    if ($bot_to_show === false) {
        return;
    }
    
    // Handle consent for display
    $consent_category = 'marketing';
    $has_consent = true;
    
    if (
        isset($this->options['complianz_toggle']) && 
        $this->options['complianz_toggle'] === 'on' &&
        function_exists('cmplz_has_consent')
    ) {
        $has_consent = cmplz_has_consent($consent_category);
    }
    
    // Display the appropriate chatbot (always floating for auto-append)
    if ($bot_to_show && $bot_to_show !== 'default') {
        // Show specific bot
        echo do_shortcode('[mxchat_chatbot floating="yes" bot_id="' . esc_attr($bot_to_show) . '" has_consent="' . ($has_consent ? 'yes' : 'no') . '"]');
    } else {
        // Show default bot
        echo do_shortcode('[mxchat_chatbot floating="yes" has_consent="' . ($has_consent ? 'yes' : 'no') . '"]');
    }
}

    private function mxchat_get_user_identifier() {
        return sanitize_text_field($_SERVER['REMOTE_ADDR']);
    }

/**
 * UPDATED: Enhanced shortcode with context-aware hiding
 */
public function render_chatbot_shortcode($atts) {
    // UPDATED: Add bot_id parameter support and improve logic
    $attributes = shortcode_atts(array(
        'floating' => 'yes',
        'has_consent' => 'yes',
        'bot_id' => '' // Support for multi-bot functionality - empty means auto-detect
    ), $atts);
    
    // Determine which bot to use
    $bot_id = $this->determine_bot_for_shortcode($attributes['bot_id']);
    
    // UPDATED: Only check hiding for floating shortcodes that could conflict with auto-append
    // Non-floating shortcodes should always work
    if ($attributes['floating'] === 'yes') {
        // For floating shortcodes, check if auto-append is hidden
        // This prevents duplicate floating chatbots
        if ($this->should_hide_chatbot('auto') && $this->is_auto_append_enabled()) {
            // If auto-append is enabled but hidden on this page,
            // allow the floating shortcode to work (user is overriding)
            // But if auto-append is disabled globally, also allow shortcode
        }
    }
    
    // Non-floating shortcodes (floating="no") should NEVER be blocked by the hide setting
    // This allows embedded chatbots even when floating is hidden
    
    $is_floating = $attributes['floating'] === 'yes';
    $bot_id = sanitize_key($bot_id); // Sanitize bot ID
    
    // Rest of your existing shortcode rendering logic continues unchanged...
    // [All the existing HTML generation code remains the same]
    
    // Get bot-specific options if multi-bot add-on is active
    $bot_options = $this->get_bot_options($bot_id);
    
    // Use bot-specific options or fall back to default options
    $current_options = !empty($bot_options) ? $bot_options : $this->options;
    
    // [Rest of your existing rendering code stays exactly the same]
    // Just remove the old should_hide_chatbot() check from the beginning
    
    // Check for Complianz consent if the toggle is enabled
    $initial_visibility = 'hidden';
    $additional_class = '';
    
    if (isset($current_options['complianz_toggle']) && $current_options['complianz_toggle'] === 'on') {
        $additional_class = ' no-consent';
    }
    $visibility_class = $initial_visibility . $additional_class;
    
    $theme_options = get_option('mxchat_theme_options', array());
    $custom_send_image = isset($theme_options['custom_send_button_image']) ? esc_url($theme_options['custom_send_button_image']) : '';
    $send_width = isset($theme_options['send_button_width']) ? intval($theme_options['send_button_width']) : 24;
    $send_height = isset($theme_options['send_button_height']) ? intval($theme_options['send_button_height']) : 24;
    $send_rotation = isset($theme_options['send_button_rotation']) ? intval($theme_options['send_button_rotation']) : 0;

    // Check if an AI theme is active (global or bot-specific) - if so, skip inline color styles
    $ai_theme_active = !empty($theme_options['active_ai_theme_css']);
    $bot_has_theme = isset($theme_options['bot_theme_assignments'][$bot_id]);
    $skip_inline_colors = $ai_theme_active || $bot_has_theme;

    // UPDATED: Use current_options instead of $this->options throughout
    $bg_color = $current_options['chatbot_background_color'] ?? '#fff';
    $user_message_bg_color = $current_options['user_message_bg_color'] ?? '#fff';
    $user_message_font_color = $current_options['user_message_font_color'] ?? '#212121';
    $bot_message_bg_color = $current_options['bot_message_bg_color'] ?? '#212121';
    $bot_message_font_color = $current_options['bot_message_font_color'] ?? '#fff';
    $top_bar_bg_color = $current_options['top_bar_bg_color'] ?? '#212121';
    $send_button_font_color = $current_options['send_button_font_color'] ?? '#212121';
    $intro_message = $current_options['intro_message'] ?? esc_html__('Hello! How can I assist you today?', 'mxchat');
    $top_bar_title = $current_options['top_bar_title'] ?? esc_html__('MxChat: Basic', 'mxchat');
    $chatbot_background_color = $current_options['chatbot_background_color'] ?? '#212121';
    $icon_color = $current_options['icon_color'] ?? '#fff';
    $chat_input_font_color = $current_options['chat_input_font_color'] ?? '#212121';
    $close_button_color = $current_options['close_button_color'] ?? '#fff';
    $chatbot_bg_color = $current_options['chatbot_bg_color'] ?? '#fff';
    $pre_chat_message = isset($current_options['pre_chat_message']) ? sanitize_text_field(trim($current_options['pre_chat_message'])) : '';
    $user_id = sanitize_key($this->mxchat_get_user_identifier());
    $email_state = $this->determine_email_collection_state();
    $show_email_form = $email_state['show_email_form'];
    $user_email = $email_state['user_email'] ?? '';
    $user_name = $email_state['user_name'] ?? '';
    $transient_key = 'mxchat_pre_chat_message_dismissed_' . $user_id;
    $input_copy = isset($current_options['input_copy']) ? esc_attr($current_options['input_copy']) : esc_attr__('How can I assist?', 'mxchat');
    $rate_limit_message = isset($current_options['rate_limit_message']) ? esc_attr($current_options['rate_limit_message']) : esc_attr__('Rate limit exceeded. Please try again later.', 'mxchat');
    $mode_indicator_bg_color = $current_options['mode_indicator_bg_color'] ?? '#212121';
    $mode_indicator_font_color = $current_options['mode_indicator_font_color'] ?? '#fff';
    $quick_questions_toggle_color = $current_options['quick_questions_toggle_color'] ?? '#212121';

    $privacy_toggle = isset($current_options['privacy_toggle']) && $current_options['privacy_toggle'] === 'on';
    $privacy_text = isset($current_options['privacy_text']) ? wp_kses_post($current_options['privacy_text']) : wp_kses_post(__('By chatting, you agree to our <a href="https://example.com/privacy-policy" target="_blank">privacy policy</a>.', 'mxchat'));

    $popular_question_1 = isset($current_options['popular_question_1']) ? esc_html($current_options['popular_question_1']) : '';
    $popular_question_2 = isset($current_options['popular_question_2']) ? esc_html($current_options['popular_question_2']) : '';
    $popular_question_3 = isset($current_options['popular_question_3']) ? esc_html($current_options['popular_question_3']) : '';
    $additional_questions = isset($current_options['additional_popular_questions']) ? $current_options['additional_popular_questions'] : [];
    $custom_icon = isset($current_options['custom_icon']) ? esc_url($current_options['custom_icon']) : '';
    $title_icon = isset($current_options['title_icon']) ? esc_url($current_options['title_icon']) : '';
    // AI agent text - if explicitly set to empty string, hide the indicator entirely
    $ai_agent_text = isset($current_options['ai_agent_text']) ? $current_options['ai_agent_text'] : __('AI Agent', 'mxchat');

    $live_agent_message_bg_color = $current_options['live_agent_message_bg_color'] ?? '#212121';
    $live_agent_message_font_color = $current_options['live_agent_message_font_color'] ?? '#fff';
    $enable_email_block = isset($current_options['enable_email_block']) && 
        ($current_options['enable_email_block'] === '1' || $current_options['enable_email_block'] === 'on');

    // Add name field variables
    $enable_name_field = isset($current_options['enable_name_field']) && 
        ($current_options['enable_name_field'] === '1' || $current_options['enable_name_field'] === 'on');
    $name_field_placeholder = isset($current_options['name_field_placeholder']) ? 
        esc_attr($current_options['name_field_placeholder']) : 
        esc_attr__('Enter your name', 'mxchat');

    ob_start();

    // Check if floating attribute is set to 'yes' and wrap accordingly
    if ($is_floating) {
        echo '<div id="floating-chatbot-' . esc_attr($bot_id) . '" class="floating-chatbot ' . $initial_visibility . $additional_class . '">';
    }

    // Add bot_id to the chatbot wrapper as a data attribute
    echo '<div id="mxchat-chatbot-wrapper-' . esc_attr($bot_id) . '" class="mxchat-chatbot-wrapper" data-bot-id="' . esc_attr($bot_id) . '">';
  
            echo '  <div class="chatbot-top-bar" id="exit-chat-button-' . esc_attr($bot_id) . '"' . ($skip_inline_colors ? '' : ' style="background: ' . esc_attr($top_bar_bg_color) . ';"') . '>';
            echo '      <div class="chatbot-title-container">';
            echo '          <div class="chatbot-title-group">';
            if (!empty($title_icon)) {
                echo '              <img src="' . esc_url($title_icon) . '" alt="" class="chatbot-title-icon">';
            }
            echo '              <p class="chatbot-title"' . ($skip_inline_colors ? '' : ' style="color: ' . esc_attr($close_button_color) . ';"') . '>' . esc_html($top_bar_title) . '</p>';
            echo '          </div>';
            // Only show mode indicator if ai_agent_text is not empty
            if (!empty(trim($ai_agent_text))) {
                echo '<span class="chat-mode-indicator" id="chat-mode-indicator-' . esc_attr($bot_id) . '" data-ai-text="' . esc_attr($ai_agent_text) . '"' . ($skip_inline_colors ? '' : ' style="color: ' . esc_attr($mode_indicator_font_color) . '; background-color: ' . esc_attr($mode_indicator_bg_color) . ';"') . '>' . esc_html($ai_agent_text) . '</span>';
            }
            echo '      </div>';
            echo '      <button class="exit-chat" type="button" aria-label="' . esc_attr__('Minimize', 'mxchat') . '"' . ($skip_inline_colors ? '' : ' style="color: ' . esc_attr($close_button_color) . ';"') . '>';
            echo '          <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24" id="ic-minimize"' . ($skip_inline_colors ? '' : ' style="fill: ' . esc_attr($close_button_color) . ';"') . '>';
            echo '              <path d="M0 0h24v24H0z" fill="none"></path>';
            echo '              <path d="M11.67 3.87L9.9 2.1 0 12l9.9 9.9 1.77-1.77L3.54 12z"></path>';
            echo '          </svg>';
            echo '          <span>' . esc_html__('Minimize', 'mxchat') . '</span>';
            echo '      </button>';
            echo '  </div>';

            // 3b) Main chatbot container
            echo '  <div id="mxchat-chatbot-' . esc_attr($bot_id) . '" class="mxchat-chatbot"' . ($skip_inline_colors ? '' : ' style="background-color: ' . esc_attr($chatbot_bg_color) . ';"') . '>';
                
            if ($enable_email_block) {
                echo '<div id="email-blocker-' . esc_attr($bot_id) . '" class="email-blocker" style="' . ($show_email_form ? '' : 'display: none;') . '">';
                echo '    <div class="email-blocker-header">';

                $header_html = isset($current_options['email_blocker_header_content'])
                    ? $current_options['email_blocker_header_content']
                    : '';
                echo wp_kses_post($header_html);

                echo '    </div>';
                echo '    <form id="email-collection-form-' . esc_attr($bot_id) . '" class="email-collection-form" method="POST" action="">';

                //   Add name field if enabled
                if ($enable_name_field) {
                    echo '        <label for="user-name-' . esc_attr($bot_id) . '" class="sr-only mxchat-name-label">' . esc_html__('Name', 'mxchat') . '</label>';
                    echo '        <input type="text" id="user-name-' . esc_attr($bot_id) . '" name="user_name" class="mxchat-name-input" required placeholder="' . $name_field_placeholder . '" />';
                }

                echo '        <label for="user-email-' . esc_attr($bot_id) . '" class="sr-only">' . esc_html__('Email Address', 'mxchat') . '</label>';
                echo '        <input type="email" id="user-email-' . esc_attr($bot_id) . '" name="user_email" class="mxchat-email-input" required placeholder="' . esc_attr__('Enter your email address', 'mxchat') . '" />';
                echo '<button type="submit" id="email-submit-button-' . esc_attr($bot_id) . '" class="email-submit-button">';
                    $button_text = isset($current_options['email_blocker_button_text'])
                        ? $current_options['email_blocker_button_text']
                        : esc_html__('Start Chat', 'mxchat');
                    echo esc_html($button_text);
                echo '</button>';
                echo '    </form>';
                echo '</div>';
            }
        
            echo '      <div id="chat-container-' . esc_attr($bot_id) . '" class="chat-container" style="' . ($enable_email_block && $show_email_form ? 'display: none;' : '') . '">';
            echo '          <div id="chat-box-' . esc_attr($bot_id) . '" class="chat-box">';
            echo '              <div class="bot-message"' . ($skip_inline_colors ? '' : ' style="background: ' . esc_attr($bot_message_bg_color) . ';"') . '>';
            echo '                  <div dir="auto"' . ($skip_inline_colors ? '' : ' style="color: ' . esc_attr($bot_message_font_color) . ';"') . '>';
            echo                        wp_kses_post($intro_message);
            echo '                  </div>';
            echo '              </div>';
            echo '          </div>';  // end #chat-box
            
            
            // Replace the existing popular questions section with this:
            echo '          <div id="mxchat-popular-questions-' . esc_attr($bot_id) . '" class="mxchat-popular-questions">';
            echo '              <div class="mxchat-popular-questions-container">';
            
            // Collapse button (down arrow) - shows when open, centered at top
            echo '                  <button class="questions-collapse-btn" aria-label="' . esc_attr__('Hide Quick Questions', 'mxchat') . '">';
            echo '                      <svg width="25" height="25" viewBox="0 0 24 24" fill="none"' . ($skip_inline_colors ? '' : ' stroke="' . esc_attr($quick_questions_toggle_color) . '"') . ' stroke-width="2">';
            echo '                          <polyline points="6,9 12,15 18,9"></polyline>';
            echo '                      </svg>';
            echo '                  </button>';

            // Expand button (up arrow) - shows when collapsed
            echo '                  <button class="questions-toggle-btn" aria-label="' . esc_attr__('Show Quick Questions', 'mxchat') . '">';
            echo '                      <svg width="25" height="25" viewBox="0 0 24 24" fill="none"' . ($skip_inline_colors ? '' : ' stroke="' . esc_attr($quick_questions_toggle_color) . '"') . ' stroke-width="2">';
            echo '                          <polyline points="18,15 12,9 6,15"></polyline>';
            echo '                      </svg>';
            echo '                  </button>';
            
            if (!empty($popular_question_1)) {
                echo '<button class="mxchat-popular-question" dir="auto">' . esc_html($popular_question_1) . '</button>';
            }
            if (!empty($popular_question_2)) {
                echo '<button class="mxchat-popular-question" dir="auto">' . esc_html($popular_question_2) . '</button>';
            }
            if (!empty($popular_question_3)) {
                echo '<button class="mxchat-popular-question" dir="auto">' . esc_html($popular_question_3) . '</button>';
            }
            
            if (!empty($additional_questions) && is_array($additional_questions)) {
                foreach ($additional_questions as $index => $question) {
                    if (!empty($question)) {
                        echo '<button class="mxchat-popular-question" dir="auto">' . esc_html($question) . '</button>';
                    }
                }
            }
            
            echo '              </div>';
            echo '          </div>';
    
            echo '          <div id="input-container-' . esc_attr($bot_id) . '" class="input-container">';
            echo '              <textarea id="chat-input-' . esc_attr($bot_id) . '" class="chat-input" dir="auto" placeholder="' . esc_attr($input_copy) . '"' . ($skip_inline_colors ? '' : ' style="color: ' . esc_attr($chat_input_font_color) . ';"') . '></textarea>';
            echo '              <button id="send-button-' . esc_attr($bot_id) . '" class="send-button" aria-label="' . esc_attr__('Send message', 'mxchat') . '">';
            if (!empty($custom_send_image)) {
                echo '                  <img src="' . esc_url($custom_send_image) . '" alt="' . esc_attr__('Send', 'mxchat') . '" style="width: ' . intval($send_width) . 'px; height: ' . intval($send_height) . 'px; transform: rotate(' . intval($send_rotation) . 'deg);" />';
            } else {
                $send_svg_style = 'width: ' . intval($send_width) . 'px; height: ' . intval($send_height) . 'px; transform: rotate(' . intval($send_rotation) . 'deg);';
                if (!$skip_inline_colors) {
                    $send_svg_style = 'fill: ' . esc_attr($send_button_font_color) . '; ' . $send_svg_style;
                }
                echo '                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="' . $send_svg_style . '">';
                echo '                      <path d="M498.1 5.6c10.1 7 15.4 19.1 13.5 31.2l-64 416c-1.5 9.7-7.4 18.2-16 23s-18.9 5.4-28 1.6L284 427.7l-68.5 74.1c-8.9 9.7-22.9 12.9-35.2 8.1S160 493.2 160 480V396.4c0-4 1.5-7.8 4.2-10.7L331.8 202.8c5.8-6.3 5.6-16-.4-22s-15.7-6.4-22-.7L106 360.8 17.7 316.6C7.1 311.3 .3 300.7 0 288.9s5.9-22.8 16.1-28.7l448-256c10.7-6.1 23.9-5.5 34 1.4z"></path>';
                echo '                  </svg>';
            }
            echo '              </button>';
            echo '          </div>';
            
            echo '          <div class="chat-toolbar">';
            
            // PDF Upload Button - wrapped in conditional using current_options
            $show_pdf_button = isset($current_options['show_pdf_upload_button']) ? $current_options['show_pdf_upload_button'] : 'on';
            if ($show_pdf_button === 'on') {
                echo '              <input type="file" id="pdf-upload-' . esc_attr($bot_id) . '" class="pdf-upload" accept=".pdf" style="display: none;">';
                echo '              <button id="pdf-upload-btn-' . esc_attr($bot_id) . '" class="toolbar-btn pdf-upload-btn" title="' . esc_attr__('Upload PDF', 'mxchat') . '">';
                echo '                  <!-- Icon from Font Awesome Free: https://fontawesome.com/license/free -->';
                echo '                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" stroke="currentColor">';
                echo '                      <path d="M64 464l48 0 0 48-48 0c-35.3 0-64-28.7-64-64L0 64C0 28.7 28.7 0 64 0L229.5 0c17 0 33.3 6.7 45.3 18.7l90.5 90.5c12 12 18.7 28.3 18.7 45.3L384 304l-48 0 0-144-80 0c-17.7 0-32-14.3-32-32l0-80L64 48c-8.8 0-16 7.2-16 16l0 384c0 8.8 7.2 16 16 16zM176 352l32 0c30.9 0 56 25.1 56 56s-25.1 56-56 56l-16 0 0 32c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-48 0-80c0-8.8 7.2-16 16-16zm32 80c13.3 0 24-10.7 24-24s-10.7-24-24-24l-16 0 0 48 16 0zm96-80l32 0c26.5 0 48 21.5 48 48l0 64c0 26.5-21.5 48-48 48l-32 0c-8.8 0-16-7.2-16-16l0-128c0-8.8 7.2-16 16-16zm32 128c8.8 0 16-7.2 16-16l0-64c0-8.8-7.2-16-16-16l-16 0 0 96 16 0zm80-112c0-8.8 7.2-16 16-16l48 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-32 0 0 32 32 0c8.8 0 16 7.2 16 16s-7.2 16-16 16l-32 0 0 48c0 8.8-7.2 16-16 16s-16-7.2-16-16l0-64 0-64z"></path>';
                echo '                  </svg>';
                echo '              </button>';
            }
            
            // Word Upload Button - wrapped in conditional using current_options
            $show_word_button = isset($current_options['show_word_upload_button']) ? $current_options['show_word_upload_button'] : 'on';
            if ($show_word_button === 'on') {
                echo '              <input type="file" id="word-upload-' . esc_attr($bot_id) . '" class="word-upload" accept=".docx" style="display: none;">';
                echo '              <button id="word-upload-btn-' . esc_attr($bot_id) . '" class="toolbar-btn word-upload-btn" title="' . esc_attr__('Upload Word Document', 'mxchat') . '">';
                echo '                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" stroke="currentColor">';
                echo '                      <path d="M48 448L48 64c0-8.8 7.2-16 16-16l160 0 0 80c0 17.7 14.3 32 32 32l80 0 0 288c0 8.8-7.2 16-16 16L64 464c-8.8 0-16-7.2-16-16zM64 0C28.7 0 0 28.7 0 64L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-293.5c0-17-6.7-33.3-18.7-45.3L274.7 18.7C262.7 6.7 246.5 0 229.5 0L64 0zm55 241.1c-3.8-12.7-17.2-19.9-29.9-16.1s-19.9 17.2-16.1 29.9l48 160c3 10.2 12.4 17.1 23 17.1s19.9-7 23-17.1l25-83.4 25 83.4c3 10.2 12.4 17.1 23 17.1s19.9-7 23-17.1l48-160c3.8-12.7-3.4-26.1-16.1-29.9s-26.1 3.4-29.9 16.1l-25 83.4-25-83.4c-3-10.2-12.4-17.1-23-17.1s-19.9 7-23 17.1l-25 83.4-25-83.4z"/></svg>';
                echo '              </button>';
            }
                    
            // File containers
            echo '              <div id="active-pdf-container-' . esc_attr($bot_id) . '" class="active-pdf-container" style="display: none;">';
            echo '                  <span id="active-pdf-name-' . esc_attr($bot_id) . '" class="active-pdf-name"></span>';
            echo '                  <button id="remove-pdf-btn-' . esc_attr($bot_id) . '" class="remove-pdf-btn" title="' . esc_attr__('Remove PDF', 'mxchat') . '">';
            echo '                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2">';
            echo '                          <line x1="18" y1="6" x2="6" y2="18"></line>';
            echo '                          <line x1="6" y1="6" x2="18" y2="18"></line>';
            echo '                      </svg>';
            echo '                  </button>';
            echo '              </div>';

            echo '              <div id="active-word-container-' . esc_attr($bot_id) . '" class="active-word-container" style="display: none;">';
            echo '                  <span id="active-word-name-' . esc_attr($bot_id) . '" class="active-word-name"></span>';
            echo '                  <button id="remove-word-btn-' . esc_attr($bot_id) . '" class="remove-word-btn" title="' . esc_attr__('Remove Word Document', 'mxchat') . '">';
            echo '                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2">';
            echo '                          <line x1="18" y1="6" x2="6" y2="18"></line>';
            echo '                          <line x1="6" y1="6" x2="18" y2="18"></line>';
            echo '                      </svg>';
            echo '                  </button>';
            echo '              </div>';
            
            // Perplexity Button
            if (apply_filters('mxchat_perplexity_should_show_logo', true)) {
                echo '              <button id="perplexity-search-btn-' . esc_attr($bot_id) . '" class="toolbar-btn perplexity-search-btn" title="' . esc_attr__('Search with Perplexity', 'mxchat-perplexity') . '">';
                echo '                  <svg fill="currentColor" height="1em" viewBox="0 0 24 24" width="1em" xmlns="http://www.w3.org/2000/svg">';
                echo '                      <path d="M19.785 0v7.272H22.5V17.62h-2.935V24l-7.037-6.194v6.145h-1.091v-6.152L4.392 24v-6.465H1.5V7.188h2.884V0l7.053 6.494V.19h1.09v6.49L19.786 0zm-7.257 9.044v7.319l5.946 5.234V14.44l-5.946-5.397zm-1.099-.08l-5.946 5.398v7.235l5.946-5.234V8.965zm8.136 7.58h1.844V8.349H13.46l6.105 5.54v2.655zm-8.982-8.28H2.59v8.195h1.8v-2.576l6.192-5.62zM5.475 2.476v4.71h5.115l-5.115-4.71zm13.219 0l-5.115 4.71h5.115v-4.71z"></path>';
                echo '                  </svg>';
                echo '              </button>';
            }
            
                    
            // Image Analysis Button - hidden by default, controlled by MxChat Vision add-on
            echo '              <input type="file" id="image-upload-' . esc_attr($bot_id) . '" class="image-upload" accept="image/*" style="display: none;" multiple>';
            echo '              <button id="image-upload-btn-' . esc_attr($bot_id) . '" class="toolbar-btn image-upload-btn" title="' . esc_attr__('Upload Image for Analysis', 'mxchat') . '" style="display: none;">';
            echo '                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="fill: none !important;">';
            echo '                      <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z" style="fill: none !important;"/>';
            echo '                      <circle cx="12" cy="13" r="3" style="fill: none !important;"/>';
            echo '                  </svg>';
            echo '              </button>';
            
            
            echo '          </div>';
            
            echo '          <div class="chatbot-footer">';
    
                    // Output the privacy notice if enabled
                    if ($privacy_toggle && !empty($privacy_text)) {
                        echo '<p class="privacy-notice">' . $privacy_text . '</p>';
                    }
    
            echo '          </div>';
            echo '      </div>';
            echo '  </div>';
            echo '</div>';
    
            if ($is_floating) {
                echo '</div>';
    
                if (!empty($pre_chat_message) && !get_transient($transient_key)) {
                    echo '<div id="pre-chat-message-' . esc_attr($bot_id) . '" class="pre-chat-message">';
                    echo esc_html($pre_chat_message);
                    echo '<button class="close-pre-chat-message" aria-label="' . esc_attr__('Close', 'mxchat') . '">&times;</button>';
                    echo '</div>';
                }

                echo '<div class="floating-chatbot-button ' . esc_attr($visibility_class) . '" id="floating-chatbot-button-' . esc_attr($bot_id) . '"' . ($skip_inline_colors ? '' : ' style="background: ' . esc_attr($chatbot_background_color) . '; color: ' . esc_attr($send_button_font_color) . ';"') . '>';
                echo '<div id="chat-notification-badge-' . esc_attr($bot_id) . '" class="chat-notification-badge" style="display: none; position: absolute; top: -8px; right: -8px; background-color: #ff4444; color: white; border-radius: 50%; padding: 4px 8px; font-size: 12px; font-weight: bold; z-index: 10001;">1</div>';

                if (!empty($custom_icon)) {
                    echo '<img src="' . $custom_icon . '" alt="' . esc_attr__('Chatbot Icon', 'mxchat') . '" style="height: 48px; width: 48px; object-fit: contain;" />';
                } else {
                    $widget_icon_style = 'height: 48px; width: 48px;';
                    if (!$skip_inline_colors) {
                        $widget_icon_style .= ' fill: ' . esc_attr($icon_color) . ';';
                    }
                    echo '<svg id="widget_icon_10" style="' . $widget_icon_style . '" viewBox="0 0 1120 1120" fill="none" xmlns="http://www.w3.org/2000/svg">';
                    echo '  <path fill-rule="evenodd" clip-rule="evenodd" d="M252 434C252 372.144 302.144 322 364 322H770C831.856 322 882 372.144 882 434V614.459L804.595 585.816C802.551 585.06 800.94 583.449 800.184 581.405L763.003 480.924C760.597 474.424 751.403 474.424 748.997 480.924L711.816 581.405C711.06 583.449 709.449 585.06 707.405 585.816L606.924 622.997C600.424 625.403 600.424 634.597 606.924 637.003L707.405 674.184C709.449 674.94 711.06 676.551 711.816 678.595L740.459 756H629.927C629.648 756.476 629.337 756.945 628.993 757.404L578.197 825.082C572.597 832.543 561.403 832.543 555.803 825.082L505.007 757.404C504.663 756.945 504.352 756.476 504.073 756H364C302.144 756 252 705.856 252 644V434ZM633.501 471.462C632.299 468.212 627.701 468.212 626.499 471.462L619.252 491.046C618.874 492.068 618.068 492.874 617.046 493.252L597.462 500.499C594.212 501.701 594.212 506.299 597.462 507.501L617.046 514.748C618.068 515.126 618.874 515.932 619.252 516.954L626.499 536.538C627.701 539.788 632.299 539.788 633.501 536.538L640.748 516.954C641.126 515.932 641.932 515.126 642.954 514.748L662.538 507.501C665.788 506.299 665.788 501.701 662.538 500.499L642.954 493.252C641.932 492.874 641.126 492.068 640.748 491.046L633.501 471.462Z" ></path>';
                    echo '  <path d="M771.545 755.99C832.175 755.17 881.17 706.175 881.99 645.545L804.595 674.184C802.551 674.94 800.94 676.551 800.184 678.595L771.545 755.99Z" ></path>';
                    echo '</svg>';
                }
                echo '</div>';
            }
    
            return ob_get_clean();
        } 
    
    /**
     * Get bot-specific options for multi-bot functionality
     * Falls back to default options if bot_id is 'default' or multi-bot add-on is not active
     */
    private function get_bot_options($bot_id = 'default') {
        // If default bot or multi-bot add-on not active, return empty (use default options)
        if ($bot_id === 'default' || !class_exists('MxChat_Multi_Bot_Manager')) {
            return array();
        }
        
        //Hook for multi-bot add-on to provide bot-specific options
        $bot_options = apply_filters('mxchat_get_bot_options', array(), $bot_id);
        
        return is_array($bot_options) ? $bot_options : array();
    }
    
    /**
     * Get bot-specific Pinecone configuration
     * Used in the knowledge retrieval functions
     */
    private function get_bot_pinecone_config($bot_id = 'default') {
        // If default bot or multi-bot add-on not active, use default Pinecone config
        if ($bot_id === 'default' || !class_exists('MxChat_Multi_Bot_Manager')) {
            $addon_options = get_option('mxchat_pinecone_addon_options', array());
            return array(
                'use_pinecone' => (isset($addon_options['mxchat_use_pinecone']) && $addon_options['mxchat_use_pinecone'] === '1'),
                'api_key' => $addon_options['mxchat_pinecone_api_key'] ?? '',
                'host' => $addon_options['mxchat_pinecone_host'] ?? '',
                'namespace' => $addon_options['mxchat_pinecone_namespace'] ?? ''
            );
        }
        
        //   Hook for multi-bot add-on to provide bot-specific Pinecone config
        $bot_pinecone_config = apply_filters('mxchat_get_bot_pinecone_config', array(), $bot_id);
        
        return is_array($bot_pinecone_config) ? $bot_pinecone_config : array();
    }
    
        
    private function determine_email_collection_state() {
    // Get session ID
    $session_id = 'mxchat_chat_' . wp_generate_uuid4(); // You'll need to use your actual session generation logic
    
    // Check if user is logged in first
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        return [
            'show_email_form' => false,
            'user_email' => $current_user->user_email,
            'user_name' => $current_user->display_name ?: $current_user->first_name ?: ''
        ];
    }
    
    // Check stored email for session
    $stored_email = get_option("mxchat_email_{$session_id}", '');
    $stored_name = get_option("mxchat_name_{$session_id}", '');
    
    // Check if name is required
    $name_field_enabled = isset($this->options['enable_name_field']) && 
        ($this->options['enable_name_field'] === '1' || $this->options['enable_name_field'] === 'on');
    
    $has_required_info = !empty($stored_email);
    if ($name_field_enabled) {
        $has_required_info = $has_required_info && !empty($stored_name);
    }
    
    return [
        'show_email_form' => !$has_required_info,
        'user_email' => $stored_email,
        'user_name' => $stored_name
    ];
}
    
    
 /**
 * NEW: Determine if and which chatbot should be displayed
 */
private function get_display_bot() {
    // Get page-specific settings from meta box
    $page_setting = $this->get_page_bot_setting();

    // Get global settings - FIXED: Check for 'on' instead of 'on'
    $global_autoshow = isset($this->options['append_to_body']) && $this->options['append_to_body'] === 'on';
    $global_default_bot = isset($this->options['default_bot']) ? $this->options['default_bot'] : 'default';

    // If page specifically hides chatbot, don't show anything
    if ($page_setting && $page_setting['action'] === 'hide') {
        return false;
    }

    // If page specifies a specific bot, use that
    if ($page_setting && $page_setting['action'] === 'show') {
        return $page_setting['bot_id'];
    }

    // Page setting is 'global' or no page setting exists
    // Check global auto-show setting
    if ($global_autoshow) {
        // Check post type visibility settings
        if (!$this->should_show_on_current_post_type()) {
            return false;
        }

        // Global auto-show is enabled, return the default bot
        return $global_default_bot;
    }

    // Global auto-show is disabled and no page-specific bot selected
    // Don't show chatbot (user should use shortcodes)
    return false;
}

/**
 * Check if chatbot should be shown on the current post type
 */
private function should_show_on_current_post_type() {
    // Get visibility settings
    $mode = isset($this->options['post_type_visibility_mode']) ? $this->options['post_type_visibility_mode'] : 'all';
    $list = isset($this->options['post_type_visibility_list']) ? $this->options['post_type_visibility_list'] : array();

    // Ensure list is an array
    if (!is_array($list)) {
        $list = array();
    }

    // If mode is 'all', show on all post types
    if ($mode === 'all') {
        return true;
    }

    // Get current post type
    $current_post_type = $this->get_current_post_type();

    // If we can't determine post type, default to showing
    if (empty($current_post_type)) {
        return true;
    }

    // Check based on mode
    if ($mode === 'include') {
        // Only show on selected post types
        return in_array($current_post_type, $list);
    } elseif ($mode === 'exclude') {
        // Hide on selected post types
        return !in_array($current_post_type, $list);
    }

    // Default to showing
    return true;
}

/**
 * Get the current post type
 */
private function get_current_post_type() {
    // Try to get from queried object first
    $queried_object = get_queried_object();

    if ($queried_object instanceof WP_Post) {
        return $queried_object->post_type;
    }

    // Try get_post_type()
    $post_type = get_post_type();
    if ($post_type) {
        return $post_type;
    }

    // Check if we're on an archive
    if (is_post_type_archive()) {
        return get_query_var('post_type');
    }

    // Check common archive types
    if (is_home() || is_single()) {
        return 'post';
    }

    if (is_page()) {
        return 'page';
    }

    return '';
}

/**
 * NEW: Get page-specific bot setting
 */
private function get_page_bot_setting($post_id = null) {
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

/**
 * NEW: Determine which bot to use for shortcode
 */
private function determine_bot_for_shortcode($shortcode_bot_id) {
    // If bot_id explicitly provided in shortcode, use that
    if (!empty($shortcode_bot_id)) {
        return $shortcode_bot_id;
    }
    
    // No bot_id in shortcode, check page setting
    $page_setting = $this->get_page_bot_setting();
    if ($page_setting && $page_setting['action'] === 'show') {
        return $page_setting['bot_id'];
    }
    
    // Fall back to default
    return 'default';
}

/**
 * NEW: Helper to check if auto-append is enabled globally
 */
private function is_auto_append_enabled() {
    return isset($this->options['append_to_body']) && $this->options['append_to_body'] === 'on';
}

/**
 * UPDATED: Debug function with new context info
 */
public function debug_display_logic() {
    if (current_user_can('manage_options') && isset($_GET['mxchat_debug'])) {
        $bot_to_show = $this->get_display_bot();
        $page_setting = $this->get_page_bot_setting();
        $global_autoshow = isset($this->options['append_to_body']) && $this->options['append_to_body'] === 'on';
        $hide_auto = $this->should_hide_chatbot('auto');
        
        echo '<div style="position: fixed; top: 50px; right: 20px; background: white; border: 2px solid red; padding: 10px; z-index: 9999; max-width: 300px; font-size: 12px;">';
        echo '<h4 style="margin: 0 0 10px 0;">MxChat Debug Info</h4>';
        echo '<p><strong>Raw append_to_body value:</strong> "' . ($this->options['append_to_body'] ?? 'NOT SET') . '"</p>';
        echo '<p><strong>Page Setting:</strong><br><pre>' . print_r($page_setting, true) . '</pre></p>';
        echo '<p><strong>Global Auto-show:</strong> ' . ($global_autoshow ? 'ON' : 'OFF') . '</p>';
        echo '<p><strong>Hide Auto-Append:</strong> ' . ($hide_auto ? 'YES' : 'NO') . '</p>';
        echo '<p><strong>Bot to Show:</strong> ' . ($bot_to_show === false ? 'NONE' : $bot_to_show) . '</p>';
        echo '<p><strong>Shortcodes:</strong> floating="no" always works</p>';
        echo '<p><small>Add ?mxchat_debug=1 to URL to see this</small></p>';
        echo '</div>';
    }
}

/**
 * NEW: Helper method to get available bots (for admin notices, etc.)
 */
private function get_available_bots() {
    $bots = array('default' => __('Default Bot', 'mxchat'));
    
    // Check if multi-bot addon is active
    if (class_exists('MxChat_Multi_Bot_Manager')) {
        $multi_bot_manager = MxChat_Multi_Bot_Core_Manager::get_instance();
        $available_bots = $multi_bot_manager->get_available_bots();
        
        // Add the available bots
        foreach ($available_bots as $bot_id => $bot_name) {
            $bots[$bot_id] = $bot_name;
        }
    }
    
    return $bots;
}   
    
    
    
}
?>