<?php
/**
 * The admin-specific functionality for the Add Ons page.
 *
 * @package    MxChat
 * @subpackage MxChat/admin
 */
class MxChat_Addons {
    /**
     * Store add-on configuration data
     *
     * @var array
     */
    private $addons_config;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        $this->addons_config = array(

            'mxchat-advanced-content' => array(
                'title' => __('MxChat Advanced Content Editor', 'mxchat'),
                'sidebar_title' => __('Content Gen.', 'mxchat'),
                'description' => __('Supercharge your AI content generation workflow. Add advanced editing tools, image management, SEO meta controls, and internal linking to the MxChat Content Generator—turning simple prompts into publish-ready posts and landing pages.', 'mxchat'),
                'key_benefits' => array(
                    __('AI-powered image regeneration & uploads', 'mxchat'),
                    __('SEO meta title, description & excerpt editor', 'mxchat'),
                    __('Automatic internal linking between posts', 'mxchat')
                ),
                'license' => 'MxChat PRO',
                'accent' => '#6366f1',
                'url' => 'https://mxchat.ai/documentation-bot/',
                'download_url' => 'https://mxchat.ai/advanced-content-editor/',
                'plugin_file' => 'mxchat-advanced-content/mxchat-advanced-content.php',
                'config_page' => 'mxchat-advanced-content',
                'hero_features' => array(
                    array(
                        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
                        'title' => __('Image Management', 'mxchat'),
                        'desc' => __('Regenerate, upload, and swap AI images', 'mxchat'),
                    ),
                    array(
                        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
                        'title' => __('SEO Meta Editor', 'mxchat'),
                        'desc' => __('Title, description & excerpt controls', 'mxchat'),
                    ),
                    array(
                        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
                        'title' => __('Internal Linking', 'mxchat'),
                        'desc' => __('Auto-link to related posts & pages', 'mxchat'),
                    ),
                    array(
                        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"/></svg>',
                        'title' => __('Chat-Based Editing', 'mxchat'),
                        'desc' => __('Refine content through conversation', 'mxchat'),
                    ),
                ),
            ),

            'mxchat-theme' => array(
                'title' => __('MxChat Chat Themes', 'mxchat'),
                'description' => __('Make your chatbot uniquely yours. Generate beautiful themes instantly using simple descriptions or customize manually with precise color controls—zero coding required. Perfect for matching your brand identity.', 'mxchat'),
                'key_benefits' => array(
                    __('AI-powered theme generator', 'mxchat'),
                    __('Live preview customizer', 'mxchat'),
                    __('One-click theme application', 'mxchat')
                ),
                'license' => 'MxChat PRO',
                'accent' => '#fa73e6',
                'url' => 'https://mxchat.ai/ai-theme-customizer/',
                'plugin_file' => 'mxchat-theme/mxchat-theme.php',
                'config_page' => 'mxchat-theme-settings'
            ),

            'mxchat-admin-assistant' => array(
                'title' => __('MxChat Admin Assistant', 'mxchat'),
                'description' => __('Your AI powerhouse inside WordPress. Chat with multiple AI models, generate images, research the web, and boost productivity—all without leaving your dashboard.', 'mxchat'),
                'key_benefits' => array(
                    __('ChatGPT-like admin interface', 'mxchat'),
                    __('Generate images & research web', 'mxchat'),
                    __('Searchable chat history', 'mxchat')
                ),
                'license' => 'MxChat PRO',
                'accent' => '#fa73e6',
                'url' => 'https://mxchat.ai/ai-admin-assistant/',
                'plugin_file' => 'mxchat-admin-chat/mxchat-admin-chat.php',
                'config_page' => 'mxchat-admin-chat'
            ),

            'mxchat-veo' => array(
                'title' => __('MxChat Video Generation', 'mxchat'),
                'description' => __('Generate stunning AI videos using Google Veo 2 and Veo 3 models directly from your WordPress dashboard. Create high-quality videos from text prompts with audio, multiple aspect ratios, and optimized generation speeds.', 'mxchat'),
                'key_benefits' => array(
                    __('Multiple Veo models (Veo 2, 3, 3 Fast)', 'mxchat'),
                    __('8-second videos with native audio', 'mxchat'),
                    __('Media library integration', 'mxchat')
                ),
                'license' => 'MxChat PRO',
                'accent' => '#ff6b35',
                'url' => 'https://mxchat.ai/mxchat-veo-video-generation/',
                'plugin_file' => 'mxchat-veo/mxchat-veo.php',
                'config_page' => 'mxchat-veo'
            ),

            'mxchat-image-analysis' => array(
                'title' => __('MxChat Image Analysis', 'mxchat'),
                'description' => __('Enhance your chatbot with AI-powered image analysis. Users can upload images for intelligent descriptions, OCR text extraction, and get answers about visual content using OpenAI Vision and Grok APIs.', 'mxchat'),
                'key_benefits' => array(
                    __('Multiple AI vision models', 'mxchat'),
                    __('Drag & drop image uploads', 'mxchat'),
                    __('Custom analysis prompts', 'mxchat')
                ),
                'license' => 'MxChat PRO',
                'accent' => '#fa73e6',
                'url' => 'https://mxchat.ai/image-analysis/',
                'plugin_file' => 'mxchat-vision/mxchat-vision.php',
                'config_page' => 'mxchat-vision'
            ),

            'mxchat-forms' => array(
                'title' => __('MxChat Forms', 'mxchat'),
                'description' => __('Convert conversations into data collection. Create smart forms that trigger during chats, collect user information, and turn casual visitors into qualified leads.', 'mxchat'),
                'key_benefits' => array(
                    __('No-code form builder', 'mxchat'),
                    __('Intent-triggered activation', 'mxchat'),
                    __('Export lead data easily', 'mxchat')
                ),
                'license' => 'MxChat PRO',
                'accent' => '#fa73e6',
                'url' => 'https://mxchat.ai/forms-action/',
                'plugin_file' => 'mxchat-forms/mxchat-forms.php',
                'config_page' => 'mxchat-forms'
            ),
            
            'mxchat-multi-bot' => array(
                'title' => __('MxChat Multi-Bot Manager', 'mxchat'),
                'description' => __('Create unlimited specialized chatbots for every purpose. Deploy dedicated bots with separate knowledge bases, custom personalities, and page-specific assignments—perfect for support, sales, and department-specific assistance.', 'mxchat'),
                'key_benefits' => array(
                    __('Unlimited specialized chatbots', 'mxchat'),
                    __('Separate Pinecone knowledge bases', 'mxchat'),
                    __('Page-specific bot assignments', 'mxchat')
                ),
                'license' => 'MxChat PRO',
                'accent' => '#8b5cf6',
                'url' => 'https://mxchat.ai/multi-bot/',
                'plugin_file' => 'mxchat-multi-bot/mxchat-multi-bot.php',
                'config_page' => 'mxchat-multi-bot'
            ),

            'mxchat-trigger' => array(
                'title' => __('MxChat Trigger', 'mxchat'),
                'description' => __('Turn any element into a conversation starter. Add chat trigger buttons and links throughout your website that open MxChat and send predefined messages—perfect for CTAs, support links, and guided experiences.', 'mxchat'),
                'key_benefits' => array(
                    __('Simple data attribute triggers', 'mxchat'),
                    __('Multi-Bot targeting support', 'mxchat'),
                    __('Built-in button styles & animations', 'mxchat')
                ),
                'license' => 'MxChat PRO',
                'accent' => '#3ac9d1',
                'url' => 'https://mxchat.ai/triggers/',
                'plugin_file' => 'mxchat-trigger/mxchat-trigger.php',
                'config_page' => 'mxchat-triggers'
            ),

            'mxchat-woo' => array(
                'title' => __('MxChat WooCommerce', 'mxchat'),
                'description' => __('Boost sales with AI-powered shopping assistance. Help customers find products, manage carts, and complete purchases—all through natural conversation.', 'mxchat'),
                'key_benefits' => array(
                    __('Smart product recommendations', 'mxchat'),
                    __('Cart & checkout assistance', 'mxchat'),
                    __('Order history access', 'mxchat')
                ),
                'license' => 'MxChat PRO',
                'accent' => '#fa73e6',
                'url' => 'https://mxchat.ai/woocommerce/',
                'plugin_file' => 'mxchat-woo/mxchat-woo.php',
                'config_page' => 'mxchat-woo'
            ),

            'mxchat-assistant-api' => array(
                'title' => __('MxChat Assistant API', 'mxchat'),
                'description' => __('Unlock the full power of OpenAI Assistants. Bypass MxChat\'s knowledge base and connect directly to your custom OpenAI Assistant for advanced AI capabilities and specialized responses.', 'mxchat'),
                'key_benefits' => array(
                    __('Custom OpenAI Assistant integration', 'mxchat'),
                    __('Advanced AI capabilities', 'mxchat'),
                    __('24-hour conversation persistence', 'mxchat')
                ),
                'license' => 'MxChat PRO',
                'accent' => '#00d4aa',
                'url' => 'https://mxchat.ai/openai-assistants-api/',
                'plugin_file' => 'mxchat-assistant-api/mxchat-assistant-api.php',
                'config_page' => 'mxchat-assistant-api'
            ),

            'mxchat-perplexity' => array(
                'title' => __('MxChat Perplexity', 'mxchat'),
                'description' => __('Give your chatbot real-time knowledge. Add powerful web search capabilities so your bot can answer questions about current events and time-sensitive information.', 'mxchat'),
                'key_benefits' => array(
                    __('Real-time web search', 'mxchat'),
                    __('Intent-triggered research', 'mxchat'),
                    __('Up-to-date information', 'mxchat')
                ),
                'license' => 'MxChat PRO',
                'accent' => '#fa73e6',
                'url' => 'https://quickdeploywp.com/plugin/mxchat-perplexity/',
                'plugin_file' => 'mxchat-perplexity/mxchat-perplexity.php',
                'config_page' => 'mxchat-perplexity'
            ),

            'mxchat-moderation' => array(
                'title' => __('MxChat Moderation', 'mxchat'),
                'description' => __('Keep your chat clean and professional. Block unwanted users, filter inappropriate content, and ensure your chatbot represents your brand properly.', 'mxchat'),
                'key_benefits' => array(
                    __('IP & email-based blocking', 'mxchat'),
                    __('Content filtering', 'mxchat'),
                    __('Spam protection', 'mxchat')
                ),
                'license' => 'MxChat PRO',
                'accent' => '#fa73e6',
                'url' => 'https://quickdeploywp.com/plugin/mxchat-moderation/',
                'plugin_file' => 'mxchat-moderation/mx-chat-moderation.php',
                'config_page' => 'mx-chat-moderation'
            ),

        );
    }

    /**
     * Get the addons configuration array
     *
     * @return array The addons configuration
     */
    public function get_addons_config() {
        return $this->addons_config;
    }

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'mxchat-addons',
            plugin_dir_url(__FILE__) . '../css/admin-add-ons.css',
            array(),
            MXCHAT_VERSION,
            'all'
        );
    }

    /**
     * Check if an addon is installed and active
     *
     * @param string $plugin_file The plugin's main file path
     * @return array Status information
     */
    private function get_addon_status($plugin_file) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());

        // Find the addon by iterating through configs
        $config_page = '';
        foreach ($this->addons_config as $slug => $addon) {
            if ($addon['plugin_file'] === $plugin_file) {
                $config_page = $addon['config_page'];
                break;
            }
        }

        if (isset($all_plugins[$plugin_file])) {
            if (in_array($plugin_file, $active_plugins)) {
                return array(
                    'status' => 'active',
                    'action_url' => admin_url('admin.php?page=' . $config_page),
                    'action_text' => __('Configure', 'mxchat')
                );
            } else {
                return array(
                    'status' => 'inactive',
                    'action_url' => wp_nonce_url(
                        admin_url('plugins.php?action=activate&plugin=' . $plugin_file),
                        'activate-plugin_' . $plugin_file
                    ),
                    'action_text' => __('Activate', 'mxchat')
                );
            }
        }

        return array(
            'status' => 'not-installed',
            'action_url' => '',
            'action_text' => __('Get Extension', 'mxchat')
        );
    }

    /**
     * Render the Add Ons page content.
     */
    public function render_page() {
        $this->enqueue_styles();
        // Remove the sorting logic and just use the original order
        $sorted_addons = $this->addons_config;
        
        ?>
        <div class="wrap mxchat-addons-wrapper">
                <div class="mxchat-addons-hero">
                    <h1 class="mxchat-main-title">
                        <span class="mxchat-gradient-text">Power Up</span> Your Chatbot
                    </h1>
                    <p class="mxchat-hero-subtitle">
                        <?php esc_html_e('Extend MxChat with these powerful extensions and unlock advanced features.', 'mxchat'); ?>
                    </p>
                </div>
                
                <div class="mxchat-addons-section">
                    <div class="mxchat-addons-grid">
                        <?php foreach ($sorted_addons as $slug => $addon): ?>
                            <?php $this->render_addon_card($slug, $addon); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="mxchat-cta-section">
                    <h2><?php esc_html_e('Ready to take your chatbot to the next level?', 'mxchat'); ?></h2>
                    <p><?php esc_html_e('Get MxChat PRO today and access all premium extensions at one low price.', 'mxchat'); ?></p>
                    <a href="https://mxchat.ai/" class="mxchat-cta-button" target="_blank"><?php esc_html_e('Learn More', 'mxchat'); ?></a>
                </div>
            </div>
            <?php
        }

    /**
     * Render individual add-on card
     *
     * @param string $slug The addon slug
     * @param array  $addon The addon configuration array
     */
    private function render_addon_card($slug, $addon) {
        $status_info = $this->get_addon_status($addon['plugin_file']);
        $button_url = $status_info['status'] === 'not-installed' ? $addon['url'] : $status_info['action_url'];
        $button_target = $status_info['status'] === 'not-installed' ? '_blank' : '_self';
        
        // Check if addon is deprecated
        $is_deprecated = isset($addon['status']) && $addon['status'] === 'deprecated';
        ?>
        <div class="mxchat-addon-card <?php echo $is_deprecated ? 'deprecated' : ''; ?>" style="--card-accent: <?php echo esc_attr($addon['accent']); ?>">
            <div class="mxchat-addon-badge">
                <?php if ($is_deprecated): ?>
                    <?php esc_html_e('Deprecated', 'mxchat'); ?>
                <?php else: ?>
                    <?php echo esc_html(ucfirst($addon['license'])); ?>
                <?php endif; ?>
            </div>
            <div class="mxchat-addon-content">
                <h3 class="mxchat-addon-title"><?php echo esc_html($addon['title']); ?></h3>
                <p class="mxchat-addon-description"><?php echo esc_html($addon['description']); ?></p>
                
                <?php if (!empty($addon['key_benefits'])): ?>
                <div class="mxchat-benefits-list">
                    <?php foreach ($addon['key_benefits'] as $benefit): ?>
                        <div class="mxchat-benefit-item">
                            <span class="mxchat-benefit-icon">✓</span>
                            <?php echo esc_html($benefit); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="mxchat-addon-footer">
                    <?php if ($is_deprecated): ?>
                        <div class="mxchat-status-indicator deprecated">
                            <?php esc_html_e('Now Built-in', 'mxchat'); ?>
                        </div>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=mxchat-prompts')); ?>"
                           class="mxchat-action-button deprecated"
                           target="_self">
                            <?php esc_html_e('Go to Knowledge Tab', 'mxchat'); ?>
                        </a>
                    <?php else: ?>
                        <div class="mxchat-status-indicator <?php echo esc_attr($status_info['status']); ?>">
                            <?php echo esc_html(ucfirst(str_replace('-', ' ', $status_info['status']))); ?>
                        </div>
                        <a href="<?php echo esc_url($button_url); ?>"
                           class="mxchat-action-button"
                           target="<?php echo esc_attr($button_target); ?>"
                           data-action="<?php echo esc_attr($status_info['status']); ?>">
                            <?php echo esc_html($status_info['action_text']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

}