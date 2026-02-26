<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$welcome_message = get_option('baachal_welcome_message', 'Hello! How can I help you today?');
$show_clear_history = get_option('baachal_show_clear_history', '1');
$chatbot_title = get_option('baachal_title', 'AI Chat Bot');

// Get styling options for CSS classes
$position = get_option('baachal_position', 'bottom-right');
$size = get_option('baachal_size', 'medium');
$animation_enabled = get_option('baachal_animation_enabled', '1');

// Allow filtering of widget settings
$welcome_message = apply_filters('baachal_welcome_message', $welcome_message);
$show_clear_history = apply_filters('baachal_show_clear_history', $show_clear_history);

// Generate CSS classes based on settings
$widget_classes = array(
    'chatbot-position-' . $position,
    'chatbot-size-' . $size
);

if ($animation_enabled === '1') {
    $widget_classes[] = 'chatbot-animated';
}

$widget_class_string = implode(' ', $widget_classes);

// Allow other plugins to add content before chatbot widget
do_action('baachal_before_chatbot_widget');
?>

<div id="ai-chatbot-widget" class="<?php echo esc_attr($widget_class_string); ?>">
    <?php
    // Allow other plugins to add content before toggle button
    do_action('baachal_before_toggle_button');
    ?>
    
    <div id="chatbot-toggle">
        <div class="toggle-icon chat-icon">
            <?php
            // Allow filtering of chat icon content
            $icon_url = BAACHAL_PLUGIN_URL . 'assets/images/baachal-icon.png';
            $chat_icon_content = apply_filters('baachal_chat_icon_content', '
            <img src="' . esc_url($icon_url) . '" alt="Chat" />
            ');
            echo wp_kses_post($chat_icon_content);
            ?>
        </div>
        <div class="toggle-icon close-icon">
            <?php
            // Allow filtering of close icon content
            $close_icon_url = BAACHAL_PLUGIN_URL . 'assets/images/close.svg';
            $close_icon_content = apply_filters('baachal_close_icon_content', '
            <img src="' . esc_url($close_icon_url) . '" alt="Close" />
            ');
            echo wp_kses_post($close_icon_content);
            ?>
        </div>
    </div>
    
    <?php
    // Allow other plugins to add content after toggle button
    do_action('baachal_after_toggle_button');
    ?>
    
    <div id="chatbot-container" class="chatbot-hidden">
        <?php
        // Allow other plugins to add content before header
        do_action('baachal_before_chatbot_header');
        ?>
        
        <div id="chatbot-header">
            <div class="chatbot-header-info">
                <div class="chatbot-header-icon">
                    <img src="<?php echo esc_url(BAACHAL_PLUGIN_URL . 'assets/images/baachal-icon.png'); ?>" alt="Baachal" />
                </div>
                <div class="chatbot-header-text">
                    <h4><?php echo esc_html(apply_filters('baachal_chatbot_title', $chatbot_title)); ?></h4>
                    <span class="chatbot-status">Online</span>
                </div>
            </div>
            <div class="chatbot-header-buttons">
                <?php
                // Allow other plugins to add custom header buttons before clear button
                do_action('baachal_chatbot_header_buttons_before');
                
                if ($show_clear_history == '1'): ?>
                <button id="chatbot-clear" title="Clear Chat History">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 6H5H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M8 6V4C8 3.46957 8.21071 2.96086 8.58579 2.58579C8.96086 2.21071 9.46957 2 10 2H14C14.5304 2 15.0391 2.21071 15.4142 2.58579C15.7893 2.96086 16 3.46957 16 4V6M19 6V20C19 20.5304 18.7893 21.0391 18.4142 21.4142C18.0391 21.7893 17.5304 22 17 22H7C6.46957 22 5.96086 21.7893 5.58579 21.4142C5.21071 21.0391 5 20.5304 5 20V6H19Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <?php endif;
                
                // Allow other plugins to add custom header buttons after clear button
                do_action('baachal_chatbot_header_buttons_after');
                ?>
                <button id="chatbot-close">×</button>
            </div>
        </div>
        
        <?php
        // Allow other plugins to add content after header
        do_action('baachal_after_chatbot_header');
        ?>
        
        <div id="chatbot-messages">
            <?php
            // Allow other plugins to add content before welcome message
            do_action('baachal_before_welcome_message');
            ?>
            
            <div class="message bot-message">
                <div class="message-content"><?php echo esc_html($welcome_message); ?></div>
            </div>
            
            <?php
            // Allow other plugins to add content after welcome message
            do_action('baachal_after_welcome_message');
            ?>
        </div>
        
        <?php
        // Allow other plugins to add content before input area
        do_action('baachal_before_input_area');
        ?>
        
        <div id="chatbot-input-area">
            <input type="text" id="chatbot-input" placeholder="<?php echo esc_attr(apply_filters('baachal_input_placeholder', 'Type your message...')); ?>" />
            <button id="chatbot-send"><?php
                // Allow filtering of send button content
                $send_button_content = apply_filters('baachal_send_button_content', '
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22 2L11 13" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M22 2L15 22L11 13L2 9L22 2Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                ');
                echo wp_kses_post($send_button_content);
                ?>
            </button>
        </div>
        
        <?php
        // Allow other plugins to add content after input area
        do_action('baachal_after_input_area');
        ?>
        
        <div id="chatbot-loading" class="chatbot-hidden">
            <?php
            // Allow filtering of loading content
            $loading_content = apply_filters('baachal_loading_content', '
            <div class="loading-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
            ');
            echo wp_kses_post($loading_content);
            ?>
        </div>
        
        <?php
        // Allow other plugins to add content before container end
        do_action('baachal_before_container_end');
        ?>
    </div>
    
    <?php
    // Allow other plugins to add content after chatbot container
    do_action('baachal_after_chatbot_container');
    ?>
</div>

<?php
// Allow other plugins to add content after chatbot widget
do_action('baachal_after_chatbot_widget');
?>
