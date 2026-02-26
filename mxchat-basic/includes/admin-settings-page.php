<?php
/**
 * MxChat Admin Settings Page - Redesigned with Sidebar Navigation
 *
 * @package MxChat
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the new sidebar-based admin settings page
 */
function mxchat_render_settings_page($admin_instance) {
    $is_activated = $admin_instance->is_activated();
    $options = get_option('mxchat_options', array());
    $plugin_url = plugin_dir_url(dirname(__FILE__));
    ?>
    <div class="mxch-admin-wrapper">
        <!-- Mobile Header -->
        <header class="mxch-mobile-header">
            <a href="#" class="mxch-mobile-logo">
                <div class="mxch-mobile-logo-icon">
                    <img src="<?php echo esc_url($plugin_url . 'images/icon-128x128.png'); ?>" alt="MxChat">
                </div>
                <span class="mxch-mobile-logo-text">MxChat</span>
            </a>
            <button type="button" class="mxch-mobile-menu-btn" aria-label="<?php esc_attr_e('Open menu', 'mxchat'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/></svg>
            </button>
        </header>

        <!-- Mobile Menu Overlay -->
        <div class="mxch-mobile-overlay"></div>

        <!-- Mobile Menu Modal -->
        <div class="mxch-mobile-menu">
            <div class="mxch-mobile-menu-header">
                <span class="mxch-mobile-menu-title"><?php esc_html_e('Settings', 'mxchat'); ?></span>
                <button type="button" class="mxch-mobile-menu-close" aria-label="<?php esc_attr_e('Close menu', 'mxchat'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <nav class="mxch-mobile-menu-nav">
                <!-- Configuration Section -->
                <div class="mxch-mobile-nav-section">
                    <div class="mxch-mobile-nav-section-title"><?php esc_html_e('Configuration', 'mxchat'); ?></div>
                    <button class="mxch-mobile-nav-link" data-parent="chatbot">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>
                        <span><?php esc_html_e('Chatbot', 'mxchat'); ?></span>
                        <svg class="mxch-mobile-nav-arrow" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </button>
                    <div class="mxch-mobile-nav-sub" data-parent="chatbot">
                        <button class="mxch-mobile-nav-sub-link active" data-target="chatbot-ai-models"><?php esc_html_e('AI Models', 'mxchat'); ?></button>
                        <button class="mxch-mobile-nav-sub-link" data-target="chatbot-behavior"><?php esc_html_e('Behavior', 'mxchat'); ?></button>
                        <button class="mxch-mobile-nav-sub-link" data-target="chatbot-display"><?php esc_html_e('Display', 'mxchat'); ?></button>
                        <button class="mxch-mobile-nav-sub-link" data-target="chatbot-lead-capture"><?php esc_html_e('Lead Capture', 'mxchat'); ?></button>
                        <button class="mxch-mobile-nav-sub-link" data-target="chatbot-quick-questions"><?php esc_html_e('Quick Questions', 'mxchat'); ?></button>
                        <button class="mxch-mobile-nav-sub-link" data-target="chatbot-rate-limits"><?php esc_html_e('Rate Limits', 'mxchat'); ?></button>
                    </div>
                    <button class="mxch-mobile-nav-link" data-target="api-keys">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 2-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0 3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                        <span><?php esc_html_e('API Keys', 'mxchat'); ?></span>
                    </button>
                    <button class="mxch-mobile-nav-link" data-target="optimization">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                        <span><?php esc_html_e('Optimization', 'mxchat'); ?></span>
                    </button>
                </div>
                <!-- Integrations Section -->
                <div class="mxch-mobile-nav-section">
                    <div class="mxch-mobile-nav-section-title"><?php esc_html_e('Integrations', 'mxchat'); ?></div>
                    <button class="mxch-mobile-nav-link" data-parent="integrations">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                        <span><?php esc_html_e('Integrations', 'mxchat'); ?></span>
                        <svg class="mxch-mobile-nav-arrow" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </button>
                    <div class="mxch-mobile-nav-sub" data-parent="integrations">
                        <button class="mxch-mobile-nav-sub-link" data-target="integrations-toolbar"><?php esc_html_e('Toolbar', 'mxchat'); ?></button>
                        <button class="mxch-mobile-nav-sub-link" data-target="integrations-loops"><?php esc_html_e('Loops', 'mxchat'); ?></button>
                        <button class="mxch-mobile-nav-sub-link" data-target="integrations-brave"><?php esc_html_e('Brave Search', 'mxchat'); ?></button>
                        <button class="mxch-mobile-nav-sub-link" data-target="integrations-slack"><?php esc_html_e('Slack', 'mxchat'); ?></button>
                        <button class="mxch-mobile-nav-sub-link" data-target="integrations-telegram"><?php esc_html_e('Telegram', 'mxchat'); ?></button>
                    </div>
                </div>
                <!-- Resources Section -->
                <div class="mxch-mobile-nav-section">
                    <div class="mxch-mobile-nav-section-title"><?php esc_html_e('Resources', 'mxchat'); ?></div>
                    <button class="mxch-mobile-nav-link" data-target="tutorials">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                        <span><?php esc_html_e('Tutorials', 'mxchat'); ?></span>
                    </button>
                </div>
                <?php if (!$is_activated): ?>
                <div class="mxch-mobile-menu-footer">
                    <a href="https://mxchat.ai/" target="_blank" class="mxch-mobile-upgrade-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
                        <?php esc_html_e('Upgrade to Pro', 'mxchat'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </nav>
        </div>

        <!-- Sidebar Navigation -->
        <aside class="mxch-sidebar">
            <div class="mxch-sidebar-header">
                <a href="#" class="mxch-sidebar-logo">
                    <div class="mxch-sidebar-logo-icon">
                        <img src="<?php echo esc_url($plugin_url . 'images/icon-128x128.png'); ?>" alt="MxChat">
                    </div>
                    <span class="mxch-sidebar-logo-text">MxChat</span>
                    <span class="mxch-sidebar-version">v<?php echo esc_html(MXCHAT_VERSION ?? '2.7.0'); ?></span>
                </a>
            </div>

            <nav class="mxch-sidebar-nav">
                <!-- Chatbot Section -->
                <div class="mxch-nav-section">
                    <div class="mxch-nav-section-title"><?php esc_html_e('Configuration', 'mxchat'); ?></div>

                    <div class="mxch-nav-item expanded" data-section="chatbot">
                        <button class="mxch-nav-link active">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('Chatbot', 'mxchat'); ?></span>
                            <svg class="mxch-nav-link-arrow" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </button>
                        <div class="mxch-nav-sub">
                            <button class="mxch-nav-sub-link active" data-target="chatbot-ai-models"><?php esc_html_e('AI Models', 'mxchat'); ?></button>
                            <button class="mxch-nav-sub-link" data-target="chatbot-behavior"><?php esc_html_e('Behavior', 'mxchat'); ?></button>
                            <button class="mxch-nav-sub-link" data-target="chatbot-display"><?php esc_html_e('Display', 'mxchat'); ?></button>
                            <button class="mxch-nav-sub-link" data-target="chatbot-lead-capture"><?php esc_html_e('Lead Capture', 'mxchat'); ?></button>
                            <button class="mxch-nav-sub-link" data-target="chatbot-quick-questions"><?php esc_html_e('Quick Questions', 'mxchat'); ?></button>
                            <button class="mxch-nav-sub-link" data-target="chatbot-rate-limits"><?php esc_html_e('Rate Limits', 'mxchat'); ?></button>
                        </div>
                    </div>

                    <div class="mxch-nav-item" data-section="api-keys">
                        <button class="mxch-nav-link" data-target="api-keys">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 2-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0 3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('API Keys', 'mxchat'); ?></span>
                        </button>
                    </div>

                    <div class="mxch-nav-item" data-section="optimization">
                        <button class="mxch-nav-link" data-target="optimization">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('Optimization', 'mxchat'); ?></span>
                        </button>
                    </div>
                </div>

                <!-- Integrations Section -->
                <div class="mxch-nav-section">
                    <div class="mxch-nav-section-title"><?php esc_html_e('Integrations', 'mxchat'); ?></div>

                    <div class="mxch-nav-item" data-section="integrations">
                        <button class="mxch-nav-link">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('Integrations', 'mxchat'); ?></span>
                            <svg class="mxch-nav-link-arrow" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        </button>
                        <div class="mxch-nav-sub">
                            <button class="mxch-nav-sub-link" data-target="integrations-toolbar"><?php esc_html_e('Toolbar', 'mxchat'); ?></button>
                            <button class="mxch-nav-sub-link" data-target="integrations-loops"><?php esc_html_e('Loops', 'mxchat'); ?></button>
                            <button class="mxch-nav-sub-link" data-target="integrations-brave"><?php esc_html_e('Brave Search', 'mxchat'); ?></button>
                            <button class="mxch-nav-sub-link" data-target="integrations-slack"><?php esc_html_e('Slack', 'mxchat'); ?></button>
                            <button class="mxch-nav-sub-link" data-target="integrations-telegram"><?php esc_html_e('Telegram', 'mxchat'); ?></button>
                        </div>
                    </div>
                </div>

                <!-- Resources Section -->
                <div class="mxch-nav-section">
                    <div class="mxch-nav-section-title"><?php esc_html_e('Resources', 'mxchat'); ?></div>

                    <div class="mxch-nav-item" data-section="tutorials">
                        <button class="mxch-nav-link" data-target="tutorials">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('Tutorials', 'mxchat'); ?></span>
                        </button>
                    </div>
                </div>
            </nav>

            <?php if (!$is_activated): ?>
            <div class="mxch-sidebar-footer">
                <a href="https://mxchat.ai/" target="_blank" class="mxch-sidebar-upgrade-v2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    <?php esc_html_e('Pro Upgrade', 'mxchat'); ?>
                </a>
            </div>
            <?php endif; ?>
        </aside>

        <!-- Main Content Area -->
        <main class="mxch-content">
            <?php if (!$is_activated): ?>
            <div class="mxch-pro-banner">
                <div class="mxch-pro-banner-content">
                    <h3 class="mxch-pro-banner-title"><?php esc_html_e('MxChat Pro — Lifetime Access', 'mxchat'); ?></h3>
                    <p class="mxch-pro-banner-text"><?php esc_html_e('One-time purchase. Unlock 10+ premium add-ons: Admin AI Assistant, Forms Builder, Chatbot Themes, and more.', 'mxchat'); ?></p>
                </div>
                <a href="https://mxchat.ai/" target="_blank" class="mxch-pro-banner-btn"><span><?php esc_html_e('Learn More', 'mxchat'); ?></span></a>
            </div>
            <?php endif; ?>

            <?php
            // Show theme migration notice for Pro users
            $admin_instance->show_theme_migration_banner();
            ?>

            <!-- ========================================
                 CHATBOT - AI MODELS
                 ======================================== -->
            <div id="chatbot-ai-models" class="mxch-section active">
                <div class="mxch-content-header">
                    <h1 class="mxch-content-title"><?php esc_html_e('AI Models', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Configure the AI models your chatbot will use for conversations and embeddings.', 'mxchat'); ?></p>
                </div>

                <div class="mxch-card">
                    <div class="mxch-card-body mxchat-autosave-section">
                        <?php
                        // Chat Model
                        mxchat_render_field_wrapper('model', __('Chat Model', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_model_callback();
                        }, __('Select the AI model your chatbot will use for conversations.', 'mxchat'));

                        // Embedding Model
                        mxchat_render_field_wrapper('embedding_model', __('Embedding Model', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->embedding_model_callback();
                        }, __('Vector embedding model for knowledge base matching. Changing models requires reconfiguring knowledge data.', 'mxchat'));

                        // Enable Streaming
                        mxchat_render_field_wrapper('enable_streaming', __('Enable Streaming', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->enable_streaming_toggle_callback();
                        }, __('Enable real-time streaming responses for supported models (OpenAI, Claude, DeepSeek, Grok).', 'mxchat'));

                        // Enable Web Search (OpenAI only)
                        mxchat_render_field_wrapper('enable_web_search', __('Enable Web Search', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->enable_web_search_toggle_callback();
                        }, __('Allow the chatbot to search the web for current information (OpenAI models only).', 'mxchat'));
                        ?>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 CHATBOT - BEHAVIOR
                 ======================================== -->
            <div id="chatbot-behavior" class="mxch-section">
                <div class="mxch-content-header">
                    <h1 class="mxch-content-title"><?php esc_html_e('Behavior', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Control how your AI chatbot responds and processes queries.', 'mxchat'); ?></p>
                </div>

                <div class="mxch-card">
                    <div class="mxch-card-body mxchat-autosave-section">
                        <?php
                        // AI Instructions
                        mxchat_render_field_wrapper('system_prompt_instructions', __('AI Instructions (Behavior)', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->system_prompt_instructions_callback();
                        }, __('Provide system-level instructions to guide the AI\'s behavior and responses.', 'mxchat'));

                        // Similarity Threshold
                        mxchat_render_field_wrapper('similarity_threshold', __('Similarity Threshold', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_similarity_threshold_callback();
                        }, __('Adjust threshold for content matching. Lower values = more matches, higher values = stricter matching.', 'mxchat'));

                        // RAG Sources Limit
                        mxchat_render_field_wrapper('rag_sources_limit', __('RAG Sources Limit', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_rag_sources_limit_callback();
                        }, __('Number of knowledge base sources (pages/documents) to include in AI context. Higher values provide more context but use more tokens.', 'mxchat'));

                        // Contextual Awareness
                        mxchat_render_field_wrapper('contextual_awareness', __('Contextual Awareness', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_contextual_awareness_callback();
                        }, __('Allow the chatbot to understand and reference the current page content for more relevant responses.', 'mxchat'));

                        // Citation Links
                        mxchat_render_field_wrapper('citation_links', __('Citation Links', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_citation_links_toggle_callback();
                        }, __('Allow the AI to include citation links from your knowledge database in responses. If disabled, ensure your AI Behavior settings do not mention links or the AI may fabricate URLs.', 'mxchat'));

                        // Frontend Debugger
                        mxchat_render_field_wrapper('frontend_debugger', __('Frontend Debugger', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_frontend_debugger_callback();
                        }, __('Show debug panel on frontend for admins. Displays similarity scores and query analysis.', 'mxchat'));
                        ?>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 CHATBOT - DISPLAY
                 ======================================== -->
            <div id="chatbot-display" class="mxch-section">
                <div class="mxch-content-header">
                    <h1 class="mxch-content-title"><?php esc_html_e('Display Settings', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Customize how and where your chatbot appears on your website.', 'mxchat'); ?></p>
                </div>

                <div class="mxch-card">
                    <div class="mxch-card-header">
                        <h3 class="mxch-card-title">
                            <svg class="mxch-card-title-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                            <?php esc_html_e('Visibility', 'mxchat'); ?>
                        </h3>
                    </div>
                    <div class="mxch-card-body mxchat-autosave-section">
                        <?php
                        // Auto-Display
                        mxchat_render_field_wrapper('auto_display', __('Auto-Display Chatbot', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_append_to_body_callback();
                        }, __('Show chatbot automatically on all pages. Disable to use shortcode [mxchat_chatbot floating="yes"] for floating widget or [mxchat_chatbot floating="no"] for embedded chat.', 'mxchat'));

                        // Open Links in New Tab
                        mxchat_render_field_wrapper('link_target', __('Open Links in New Tab', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_link_target_toggle_callback();
                        }, __('Open links clicked in chat responses in a new browser tab.', 'mxchat'));

                        // Chat Persistence
                        mxchat_render_field_wrapper('chat_persistence', __('Chat Persistence', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_chat_persistence_toggle_callback();
                        }, __('Keep chat history when users navigate or return within 24 hours.', 'mxchat'));
                        ?>
                    </div>
                </div>

                <div class="mxch-card">
                    <div class="mxch-card-header">
                        <h3 class="mxch-card-title">
                            <svg class="mxch-card-title-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            <?php esc_html_e('Text & Labels', 'mxchat'); ?>
                        </h3>
                    </div>
                    <div class="mxch-card-body mxchat-autosave-section">
                        <?php
                        // Top Bar Title
                        mxchat_render_field_wrapper('top_bar_title', __('Top Bar Title', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_top_bar_title_callback();
                        }, __('Title text shown in the chatbot header.', 'mxchat'));

                        // AI Agent Text
                        mxchat_render_field_wrapper('ai_agent_text', __('AI Agent Text', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_ai_agent_text_callback();
                        }, __('Text shown for AI agents in the status indicator.', 'mxchat'));

                        // Intro Message
                        mxchat_render_field_wrapper('intro_message', __('Introductory Message', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_intro_message_callback();
                        }, __('First message shown when chat opens. HTML allowed.', 'mxchat'));

                        // Input Placeholder
                        mxchat_render_field_wrapper('input_copy', __('Input Placeholder', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_input_copy_callback();
                        }, __('Placeholder text in the chat input field.', 'mxchat'));

                        // Chat Teaser
                        mxchat_render_field_wrapper('pre_chat_message', __('Chat Teaser Pop-up', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_pre_chat_message_callback();
                        }, __('Message shown before users start chatting.', 'mxchat'));
                        ?>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 CHATBOT - LEAD CAPTURE
                 ======================================== -->
            <div id="chatbot-lead-capture" class="mxch-section">
                <div class="mxch-content-header">
                    <h1 class="mxch-content-title"><?php esc_html_e('Lead Capture', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Collect user information and manage privacy settings.', 'mxchat'); ?></p>
                </div>

                <div class="mxch-card">
                    <div class="mxch-card-header">
                        <h3 class="mxch-card-title">
                            <svg class="mxch-card-title-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                            <?php esc_html_e('Email Capture', 'mxchat'); ?>
                        </h3>
                    </div>
                    <div class="mxch-card-body mxchat-autosave-section">
                        <?php
                        // Require Email
                        mxchat_render_field_wrapper('enable_email_block', __('Require Email to Chat', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->enable_email_block_callback();
                        }, __('Require users to enter their email before chatting. Shows for non-logged-in users.', 'mxchat'));

                        // Email Form Content
                        mxchat_render_field_wrapper('email_blocker_header', __('Email Form Content', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->email_blocker_header_content_callback();
                        }, __('HTML content shown above the email form.', 'mxchat'));

                        // Email Button Text
                        mxchat_render_field_wrapper('email_blocker_button', __('Submit Button Text', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->email_blocker_button_text_callback();
                        }, __('Text for the email form submit button.', 'mxchat'));

                        // Require Name
                        mxchat_render_field_wrapper('enable_name_field', __('Also Require Name', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->enable_name_field_callback();
                        }, __('Also require users to enter their name with email.', 'mxchat'));

                        // Name Placeholder
                        mxchat_render_field_wrapper('name_placeholder', __('Name Field Placeholder', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->name_field_placeholder_callback();
                        }, __('Placeholder text for the name input field.', 'mxchat'));
                        ?>
                    </div>
                </div>

                <div class="mxch-card">
                    <div class="mxch-card-header">
                        <h3 class="mxch-card-title">
                            <svg class="mxch-card-title-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <?php esc_html_e('Privacy & Compliance', 'mxchat'); ?>
                        </h3>
                    </div>
                    <div class="mxch-card-body mxchat-autosave-section">
                        <?php
                        // Privacy Notice
                        mxchat_render_field_wrapper('privacy_toggle', __('Privacy Notice', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_privacy_toggle_callback();
                        }, __('Show privacy notice below the chat widget.', 'mxchat'));

                        // Complianz
                        mxchat_render_field_wrapper('complianz_toggle', __('Complianz Integration', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_complianz_toggle_callback();
                        }, __('Apply Complianz consent logic to the chatbot (requires Complianz plugin).', 'mxchat'));
                        ?>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 CHATBOT - QUICK QUESTIONS
                 ======================================== -->
            <div id="chatbot-quick-questions" class="mxch-section">
                <div class="mxch-content-header">
                    <h1 class="mxch-content-title"><?php esc_html_e('Quick Questions', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Pre-defined questions displayed above the chat input to help users get started.', 'mxchat'); ?></p>
                </div>

                <div class="mxch-card">
                    <div class="mxch-card-body mxchat-autosave-section">
                        <?php
                        // Quick Question 1
                        mxchat_render_field_wrapper('popular_question_1', __('Quick Question 1', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_popular_question_1_callback();
                        });

                        // Quick Question 2
                        mxchat_render_field_wrapper('popular_question_2', __('Quick Question 2', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_popular_question_2_callback();
                        });

                        // Quick Question 3
                        mxchat_render_field_wrapper('popular_question_3', __('Quick Question 3', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_popular_question_3_callback();
                        });

                        // Additional Questions
                        mxchat_render_field_wrapper('additional_questions', __('Additional Questions', 'mxchat'), function() use ($admin_instance) {
                            $admin_instance->mxchat_additional_popular_questions_callback();
                        }, __('Add as many quick questions as you need.', 'mxchat'));
                        ?>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 CHATBOT - RATE LIMITS
                 ======================================== -->
            <div id="chatbot-rate-limits" class="mxch-section">
                <div class="mxch-content-header">
                    <h1 class="mxch-content-title"><?php esc_html_e('Rate Limits', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Set message limits for each user role to manage API usage.', 'mxchat'); ?></p>
                </div>

                <div class="mxch-notice mxch-notice-info" style="margin-bottom: 24px;">
                    <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <div>
                        <strong><?php esc_html_e('Placeholders available:', 'mxchat'); ?></strong>
                        <code>{limit}</code>, <code>{timeframe}</code>, <code>{count}</code>, <code>{remaining}</code>
                        <br>
                        <strong><?php esc_html_e('Markdown links supported:', 'mxchat'); ?></strong>
                        <code>[Link text](https://example.com)</code>
                    </div>
                </div>

                <div class="mxch-card">
                    <div class="mxch-card-body mxchat-autosave-section">
                        <?php
                        // Render rate limits with accordion
                        mxchat_render_rate_limits_accordion($admin_instance);
                        ?>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 API KEYS
                 ======================================== -->
            <div id="api-keys" class="mxch-section">
                <div class="mxch-content-header">
                    <h1 class="mxch-content-title"><?php esc_html_e('API Keys', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Manage API keys for AI providers and integrations.', 'mxchat'); ?></p>
                </div>

                <div class="mxch-card">
                    <div class="mxch-card-header">
                        <h3 class="mxch-card-title">
                            <svg class="mxch-card-title-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>
                            <?php esc_html_e('AI Providers', 'mxchat'); ?>
                        </h3>
                    </div>
                    <div class="mxch-card-body mxchat-autosave-section">
                        <table class="form-table">
                            <?php do_settings_fields('mxchat-api-keys', 'mxchat_api_keys_section'); ?>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 OPTIMIZATION
                 ======================================== -->
            <div id="optimization" class="mxch-section">
                <div class="mxch-content-header">
                    <h1 class="mxch-content-title"><?php esc_html_e('Optimization & Diagnostics', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Optimize performance, debug issues, and manage plugin settings.', 'mxchat'); ?></p>
                </div>

                <!-- Script Loading Card -->
                <div class="mxch-card">
                    <div class="mxch-card-header">
                        <h3 class="mxch-card-title">
                            <svg class="mxch-card-title-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                            <?php esc_html_e('Script Loading', 'mxchat'); ?>
                        </h3>
                    </div>
                    <div class="mxch-card-body mxchat-autosave-section">
                        <?php
                        $script_loading_strategy = isset($options['script_loading_strategy']) ? $options['script_loading_strategy'] : 'default';
                        ?>
                        <div class="mxch-field">
                            <label class="mxch-field-label"><?php esc_html_e('Script Loading Strategy', 'mxchat'); ?></label>
                            <div class="mxch-field-control">
                                <select name="mxchat_options[script_loading_strategy]" id="script_loading_strategy" class="mxch-select mxchat-autosave-field">
                                    <option value="default" <?php selected($script_loading_strategy, 'default'); ?>><?php esc_html_e('Default (Immediate)', 'mxchat'); ?></option>
                                    <option value="defer" <?php selected($script_loading_strategy, 'defer'); ?>><?php esc_html_e('Deferred', 'mxchat'); ?></option>
                                    <option value="delay_1s" <?php selected($script_loading_strategy, 'delay_1s'); ?>><?php esc_html_e('Delay 1 Second', 'mxchat'); ?></option>
                                    <option value="delay_3s" <?php selected($script_loading_strategy, 'delay_3s'); ?>><?php esc_html_e('Delay 3 Seconds', 'mxchat'); ?></option>
                                    <option value="delay_5s" <?php selected($script_loading_strategy, 'delay_5s'); ?>><?php esc_html_e('Delay 5 Seconds', 'mxchat'); ?></option>
                                    <option value="on_interaction" <?php selected($script_loading_strategy, 'on_interaction'); ?>><?php esc_html_e('On User Interaction', 'mxchat'); ?></option>
                                </select>
                            </div>
                            <p class="mxch-field-description"><?php esc_html_e('Control when the chatbot script loads to improve page performance.', 'mxchat'); ?></p>
                        </div>

                        <div class="mxch-notice mxch-notice-info" style="margin-top: 16px;">
                            <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <div>
                                <strong><?php esc_html_e('Loading Strategies:', 'mxchat'); ?></strong>
                                <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                                    <li><strong><?php esc_html_e('Default:', 'mxchat'); ?></strong> <?php esc_html_e('Script loads immediately in the footer. Best for sites where chat is critical.', 'mxchat'); ?></li>
                                    <li><strong><?php esc_html_e('Deferred:', 'mxchat'); ?></strong> <?php esc_html_e('Script loads after HTML parsing completes. Good balance of performance and availability.', 'mxchat'); ?></li>
                                    <li><strong><?php esc_html_e('Delay:', 'mxchat'); ?></strong> <?php esc_html_e('Script loads after a set delay. Helps improve LCP scores.', 'mxchat'); ?></li>
                                    <li><strong><?php esc_html_e('On User Interaction:', 'mxchat'); ?></strong> <?php esc_html_e('Script loads when user scrolls, moves mouse, or touches screen. Best for Core Web Vitals but chat appears with slight delay.', 'mxchat'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Debug Mode Card -->
                <?php
                $debug_mode = isset($options['debug_mode']) ? $options['debug_mode'] : 'off';
                $debug_active = ($debug_mode === 'on');
                ?>
                <div class="mxch-card">
                    <div class="mxch-card-header">
                        <h3 class="mxch-card-title">
                            <svg class="mxch-card-title-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            <?php esc_html_e('Debug Mode', 'mxchat'); ?>
                        </h3>
                    </div>
                    <div class="mxch-card-body">
                        <div class="mxch-field">
                            <label class="mxch-toggle">
                                <input type="checkbox" class="mxch-toggle-input" id="mxchat_debug_mode" name="mxchat_options[debug_mode]" value="on" <?php checked($debug_active); ?>>
                                <span class="mxch-toggle-switch"></span>
                                <span class="mxch-toggle-label"><?php esc_html_e('Enable Debug Logging', 'mxchat'); ?></span>
                            </label>
                            <p class="mxch-field-description"><?php esc_html_e('Tracks settings changes, knowledge base errors, API issues, and embedding failures. Enable only when troubleshooting. Max 100 entries stored.', 'mxchat'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Debug Log Viewer Card -->
                <div class="mxch-card">
                    <div class="mxch-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 class="mxch-card-title">
                            <svg class="mxch-card-title-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            <?php esc_html_e('Debug Log', 'mxchat'); ?>
                        </h3>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <span id="mxchat-log-count" class="mxch-badge" style="display: none;"></span>
                            <button type="button" class="mxch-btn mxch-btn-secondary mxch-btn-sm" id="mxchat-refresh-log">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/></svg>
                                <?php esc_html_e('Refresh', 'mxchat'); ?>
                            </button>
                            <button type="button" class="mxch-btn mxch-btn-secondary mxch-btn-sm" id="mxchat-clear-log">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                <?php esc_html_e('Clear', 'mxchat'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="mxch-card-body">
                        <div id="mxchat-debug-log" class="mxchat-debug-log-viewer">
                            <div class="mxchat-debug-log-empty">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.3;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                <p><?php esc_html_e('No log entries yet. Enable debug mode to start logging.', 'mxchat'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Settings Tools Card -->
                <div class="mxch-card">
                    <div class="mxch-card-header">
                        <h3 class="mxch-card-title">
                            <svg class="mxch-card-title-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                            <?php esc_html_e('Settings Tools', 'mxchat'); ?>
                        </h3>
                    </div>
                    <div class="mxch-card-body">
                        <div class="mxch-tools-grid">
                            <!-- Export Settings -->
                            <div class="mxch-tool-item">
                                <div class="mxch-tool-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                </div>
                                <div class="mxch-tool-content">
                                    <h4><?php esc_html_e('Export Settings', 'mxchat'); ?></h4>
                                    <p><?php esc_html_e('Download a JSON file of your current settings for backup or support. API keys are masked for security.', 'mxchat'); ?></p>
                                    <button type="button" class="mxch-btn mxch-btn-secondary" id="mxchat-export-settings">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                        <?php esc_html_e('Export Settings', 'mxchat'); ?>
                                    </button>
                                </div>
                            </div>

                            <!-- Reset All Settings -->
                            <div class="mxch-tool-item mxch-tool-item-danger">
                                <div class="mxch-tool-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                </div>
                                <div class="mxch-tool-content">
                                    <h4><?php esc_html_e('Reset All Settings', 'mxchat'); ?></h4>
                                    <p><?php esc_html_e('Reset all MxChat settings to their default values. This action cannot be undone.', 'mxchat'); ?></p>
                                    <button type="button" class="mxch-btn mxch-btn-danger" id="mxchat-reset-settings">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/></svg>
                                        <?php esc_html_e('Reset All Settings', 'mxchat'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reset Confirmation Modal -->
                <div id="mxchat-reset-modal" class="mxch-modal" style="display: none;">
                    <div class="mxch-modal-backdrop"></div>
                    <div class="mxch-modal-content">
                        <div class="mxch-modal-header">
                            <h3><?php esc_html_e('Reset All Settings', 'mxchat'); ?></h3>
                            <button type="button" class="mxch-modal-close" id="mxchat-reset-modal-close">&times;</button>
                        </div>
                        <div class="mxch-modal-body">
                            <div class="mxch-notice mxch-notice-warning">
                                <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                <div>
                                    <strong><?php esc_html_e('Warning: This action cannot be undone!', 'mxchat'); ?></strong>
                                    <p><?php esc_html_e('All your MxChat settings including API keys, customizations, and configurations will be permanently deleted.', 'mxchat'); ?></p>
                                </div>
                            </div>
                            <div class="mxch-field" style="margin-top: 20px;">
                                <label class="mxch-field-label"><?php esc_html_e('Type RESET to confirm:', 'mxchat'); ?></label>
                                <input type="text" id="mxchat-reset-confirmation" class="mxch-input" placeholder="RESET" autocomplete="off">
                            </div>
                        </div>
                        <div class="mxch-modal-footer">
                            <button type="button" class="mxch-btn mxch-btn-secondary" id="mxchat-reset-cancel"><?php esc_html_e('Cancel', 'mxchat'); ?></button>
                            <button type="button" class="mxch-btn mxch-btn-danger" id="mxchat-reset-confirm" disabled><?php esc_html_e('Reset All Settings', 'mxchat'); ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 INTEGRATIONS - TOOLBAR
                 ======================================== -->
            <div id="integrations-toolbar" class="mxch-section">
                <div class="mxch-content-header">
                    <h1 class="mxch-content-title"><?php esc_html_e('Toolbar Settings', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Configure the chat toolbar and document upload features.', 'mxchat'); ?></p>
                </div>

                <div class="mxch-card">
                    <div class="mxch-card-body mxchat-autosave-section">
                        <table class="form-table">
                            <?php do_settings_fields('mxchat-embed', 'mxchat_pdf_intent_section'); ?>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 INTEGRATIONS - LOOPS
                 ======================================== -->
            <div id="integrations-loops" class="mxch-section">
                <div class="mxch-content-header">
                    <h1 class="mxch-content-title"><?php esc_html_e('Loops Integration', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Configure email capture with Loops for mailing list growth.', 'mxchat'); ?></p>
                </div>

                <div class="mxch-card">
                    <div class="mxch-card-body mxchat-autosave-section">
                        <table class="form-table">
                            <?php do_settings_fields('mxchat-embed', 'mxchat_loops_section'); ?>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 INTEGRATIONS - BRAVE SEARCH
                 ======================================== -->
            <div id="integrations-brave" class="mxch-section">
                <div class="mxch-content-header">
                    <h1 class="mxch-content-title"><?php esc_html_e('Brave Search', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Configure Brave Search API for web search capabilities.', 'mxchat'); ?></p>
                </div>

                <div class="mxch-card">
                    <div class="mxch-card-body mxchat-autosave-section">
                        <table class="form-table">
                            <?php do_settings_fields('mxchat-embed', 'mxchat_brave_section'); ?>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 INTEGRATIONS - SLACK
                 ======================================== -->
            <div id="integrations-slack" class="mxch-section">
                <div class="mxch-content-header">
                    <h1 class="mxch-content-title"><?php esc_html_e('Slack', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Connect your chatbot to Slack for live human support.', 'mxchat'); ?></p>
                </div>

                <div class="mxch-notice mxch-notice-info" style="margin-bottom: 24px;">
                    <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <div>
                        <?php printf(
                            esc_html__('Visit our %s for setup instructions.', 'mxchat'),
                            '<a href="https://mxchat.ai/documentation/#slack_integration" target="_blank">' . esc_html__('documentation page', 'mxchat') . '</a>'
                        ); ?>
                    </div>
                </div>

                <div class="mxch-card">
                    <div class="mxch-card-body mxchat-autosave-section">
                        <table class="form-table">
                            <?php do_settings_fields('mxchat-embed', 'mxchat_live_agent_section'); ?>
                        </table>
                    </div>
                </div>

                <div class="mxch-card" style="margin-top: 24px;">
                    <div class="mxch-card-header">
                        <h3 class="mxch-card-title">
                            <svg class="mxch-card-title-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <?php esc_html_e('Test Connection', 'mxchat'); ?>
                        </h3>
                    </div>
                    <div class="mxch-card-body">
                        <p class="mxch-field-description" style="margin-bottom: 16px;">
                            <?php esc_html_e('Test your Slack bot token to verify it has the required permissions. This will check if the bot can authenticate and create channels.', 'mxchat'); ?>
                        </p>
                        <button type="button" id="mxchat-test-slack-connection" class="mxch-btn mxch-btn-secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <?php esc_html_e('Test Slack Connection', 'mxchat'); ?>
                        </button>
                        <div id="mxchat-slack-test-result" style="margin-top: 16px; display: none;"></div>
                    </div>
                </div>

                <div class="mxch-card" style="margin-top: 24px;">
                    <div class="mxch-card-header">
                        <h3 class="mxch-card-title">
                            <svg class="mxch-card-title-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <?php esc_html_e('Required OAuth Scopes', 'mxchat'); ?>
                        </h3>
                    </div>
                    <div class="mxch-card-body">
                        <p class="mxch-field-description" style="margin-bottom: 12px;">
                            <?php esc_html_e('Your Slack app needs these Bot Token Scopes (OAuth & Permissions > Scopes):', 'mxchat'); ?>
                        </p>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px; background: #f8f9fa; padding: 16px; border-radius: 8px; font-family: monospace; font-size: 13px;">
                            <code style="background: #e9ecef; padding: 4px 8px; border-radius: 4px;">channels:manage</code>
                            <code style="background: #e9ecef; padding: 4px 8px; border-radius: 4px;">channels:read</code>
                            <code style="background: #e9ecef; padding: 4px 8px; border-radius: 4px;">chat:write</code>
                            <code style="background: #e9ecef; padding: 4px 8px; border-radius: 4px;">chat:write.public</code>
                            <code style="background: #e9ecef; padding: 4px 8px; border-radius: 4px;">groups:write</code>
                            <code style="background: #e9ecef; padding: 4px 8px; border-radius: 4px;">users:read</code>
                        </div>
                        <p class="mxch-field-description" style="margin-top: 12px;">
                            <?php esc_html_e('After adding scopes, reinstall the app to your workspace.', 'mxchat'); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 INTEGRATIONS - TELEGRAM
                 ======================================== -->
            <div id="integrations-telegram" class="mxch-section">
                <div class="mxch-content-header">
                    <h1 class="mxch-content-title"><?php esc_html_e('Telegram', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Connect your chatbot to Telegram for live human support.', 'mxchat'); ?></p>
                </div>

                <div class="mxch-notice mxch-notice-info" style="margin-bottom: 24px;">
                    <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <div>
                        <?php esc_html_e('Telegram Live Agent requires a Telegram bot and a supergroup with forum topics enabled.', 'mxchat'); ?>
                        <a href="https://mxchat.ai/documentation-bot/" target="_blank" style="margin-left: 5px;"><?php esc_html_e('Chat with our advanced doc bot for assistance setting up.', 'mxchat'); ?></a>
                    </div>
                </div>

                <div class="mxch-card">
                    <div class="mxch-card-body mxchat-autosave-section">
                        <table class="form-table">
                            <?php do_settings_fields('mxchat-embed', 'mxchat_telegram_section'); ?>
                        </table>
                    </div>
                </div>

                <div class="mxch-card" style="margin-top: 24px;">
                    <div class="mxch-card-header">
                        <h3 class="mxch-card-title">
                            <svg class="mxch-card-title-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                            <?php esc_html_e('Webhook Setup', 'mxchat'); ?>
                        </h3>
                    </div>
                    <div class="mxch-card-body">
                        <?php
                        $telegram_bot_token = $options['telegram_bot_token'] ?? '';
                        $telegram_webhook_secret = $options['telegram_webhook_secret'] ?? '';
                        $webhook_url = rest_url('mxchat/v1/telegram-webhook');
                        ?>

                        <p class="mxch-field-description" style="margin-bottom: 8px;">
                            <strong><?php esc_html_e('Your Webhook URL:', 'mxchat'); ?></strong>
                        </p>
                        <div class="mxch-webhook-url-display" style="margin-bottom: 20px;">
                            <code id="mxch-telegram-webhook-url"><?php echo esc_url($webhook_url); ?></code>
                            <button type="button" class="mxch-btn mxch-btn-secondary mxch-btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('mxch-telegram-webhook-url').textContent); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy', 2000);">
                                <?php esc_html_e('Copy', 'mxchat'); ?>
                            </button>
                        </div>

                        <?php if (!empty($telegram_bot_token)) : ?>
                            <p class="mxch-field-description" style="margin-bottom: 8px;">
                                <strong><?php esc_html_e('Register Webhook URL:', 'mxchat'); ?></strong><br>
                                <?php esc_html_e('Copy and paste this URL into your browser to register the webhook with Telegram:', 'mxchat'); ?>
                            </p>
                            <?php
                            $registration_url = 'https://api.telegram.org/bot' . $telegram_bot_token . '/setWebhook?url=' . urlencode($webhook_url);
                            if (!empty($telegram_webhook_secret)) {
                                $registration_url .= '&secret_token=' . urlencode($telegram_webhook_secret);
                            }
                            ?>
                            <div class="mxch-webhook-url-display" style="margin-bottom: 12px;">
                                <code id="mxch-telegram-registration-url" style="word-break: break-all; font-size: 12px;"><?php echo esc_url($registration_url); ?></code>
                                <button type="button" class="mxch-btn mxch-btn-secondary mxch-btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('mxch-telegram-registration-url').textContent); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy', 2000);">
                                    <?php esc_html_e('Copy', 'mxchat'); ?>
                                </button>
                            </div>
                            <p class="mxch-field-description" style="color: #22c55e;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                <?php esc_html_e('You should see {"ok":true,"result":true,"description":"Webhook was set"} after visiting the URL.', 'mxchat'); ?>
                            </p>
                        <?php else : ?>
                            <p class="mxch-field-description" style="color: #f59e0b;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                                <?php esc_html_e('Enter your Bot Token above and save to generate the registration URL.', 'mxchat'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 TUTORIALS
                 ======================================== -->
            <div id="tutorials" class="mxch-section">
                <div class="mxch-content-header">
                    <h1 class="mxch-content-title"><?php esc_html_e('Video Tutorials', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Learn how to get the most out of MxChat with our video guides.', 'mxchat'); ?></p>
                </div>

                <div class="mxch-card" style="margin-bottom: 24px;">
                    <div class="mxch-card-body">
                        <div style="display: flex; align-items: flex-start; gap: 16px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--mxch-warning); flex-shrink: 0;"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                            <div>
                                <h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 600;"><?php esc_html_e('Need Help?', 'mxchat'); ?></h3>
                                <p style="margin: 0 0 12px 0; color: var(--mxch-text-secondary);"><?php esc_html_e('If you\'re having trouble or believe something is not working as expected, please submit a support ticket.', 'mxchat'); ?></p>
                                <a href="https://wordpress.org/support/plugin/mxchat-basic/" target="_blank" class="mxch-btn mxch-btn-secondary mxch-btn-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9a2 2 0 0 1-2 2H6l-4 4V4c0-1.1.9-2 2-2h8a2 2 0 0 1 2 2v5Z"/><path d="M18 9h2a2 2 0 0 1 2 2v11l-4-4h-6a2 2 0 0 1-2-2v-1"/></svg>
                                    <?php esc_html_e('Submit Support Ticket', 'mxchat'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mxch-tutorials-grid">
                    <?php
                    $tutorials = array(
                        array(
                            'title' => __('Quick Setup with MxChat', 'mxchat'),
                            'description' => __('Learn how to quickly setup and understand how your chatbot works.', 'mxchat'),
                            'url' => 'https://www.youtube.com/watch?v=3BqoiyWaQiM&t'
                        ),
                        array(
                            'title' => __('AI Content Generation', 'mxchat'),
                            'description' => __('Generate full blog posts and landing pages with AI—from prompt to publish in minutes.', 'mxchat'),
                            'url' => 'https://www.youtube.com/watch?v=n8TeEeHpxs4'
                        ),
                        array(
                            'title' => __('AI Theme Generator', 'mxchat'),
                            'description' => __('Learn how to instantly restyle your chatbot using plain English prompts with real-time previews.', 'mxchat'),
                            'url' => 'https://www.youtube.com/watch?v=rSQDW2qbtRU&t'
                        ),
                        array(
                            'title' => __('MxChat Forms', 'mxchat'),
                            'description' => __('Create and manage smart forms that automatically trigger during chat conversations.', 'mxchat'),
                            'url' => 'https://www.youtube.com/watch?v=3MrWy5dRalA'
                        ),
                        array(
                            'title' => __('Admin Assistant Add-on', 'mxchat'),
                            'description' => __('Bring a ChatGPT-like experience directly inside your WordPress dashboard.', 'mxchat'),
                            'url' => 'https://youtu.be/AdEA1k-UCFM'
                        ),
                        array(
                            'title' => __('Chat Themes', 'mxchat'),
                            'description' => __('Customize appearance with real-time previews to match your brand.', 'mxchat'),
                            'url' => 'https://youtu.be/MfbB9mZi6ag'
                        ),
                        array(
                            'title' => __('WooCommerce Integration', 'mxchat'),
                            'description' => __('Provide product recommendations and shopping assistance to customers.', 'mxchat'),
                            'url' => 'https://www.youtube.com/watch?v=WsqAppHRGdA'
                        ),
                        array(
                            'title' => __('Knowledge Base Setup', 'mxchat'),
                            'description' => __('Set up your knowledge base using PDFs, sitemaps, and manual entries.', 'mxchat'),
                            'url' => 'https://www.youtube.com/watch?v=8Ztjs66-VTo'
                        ),
                        array(
                            'title' => __('Document Chat', 'mxchat'),
                            'description' => __('Chat with PDF and Word documents for enhanced document analysis.', 'mxchat'),
                            'url' => 'https://www.youtube.com/watch?v=j_c45WWCTG0'
                        ),
                        array(
                            'title' => __('Perplexity Integration', 'mxchat'),
                            'description' => __('Enable real-time web search capabilities for your chatbot.', 'mxchat'),
                            'url' => 'https://youtu.be/wpKkbt24-bo'
                        ),
                        array(
                            'title' => __('Brave Search Intent', 'mxchat'),
                            'description' => __('Leverage Brave Search for improved query understanding.', 'mxchat'),
                            'url' => 'https://www.youtube.com/watch?v=7vDL5H7vToc'
                        ),
                        array(
                            'title' => __('Loops Email Capture', 'mxchat'),
                            'description' => __('Set up email capture with Loops to grow your mailing list.', 'mxchat'),
                            'url' => 'https://www.youtube.com/watch?v=CNgm5TYDyTc'
                        ),
                        array(
                            'title' => __('AI Agent Testing', 'mxchat'),
                            'description' => __('Evaluate and improve your chatbot\'s performance and accuracy.', 'mxchat'),
                            'url' => 'https://www.youtube.com/watch?v=A0jowbpyX54'
                        ),
                    );

                    foreach ($tutorials as $tutorial): ?>
                    <div class="mxch-tutorial-card">
                        <div class="mxch-tutorial-content">
                            <h3 class="mxch-tutorial-title"><?php echo esc_html($tutorial['title']); ?></h3>
                            <p class="mxch-tutorial-description"><?php echo esc_html($tutorial['description']); ?></p>
                            <a href="<?php echo esc_url($tutorial['url']); ?>" target="_blank" rel="noopener" class="mxch-tutorial-link">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                <?php esc_html_e('Watch Tutorial', 'mxchat'); ?>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sidebar navigation
        const navLinks = document.querySelectorAll('.mxch-nav-link, .mxch-nav-sub-link');
        const sections = document.querySelectorAll('.mxch-section');
        const navItems = document.querySelectorAll('.mxch-nav-item');

        function showSection(target) {
            // Show target section
            sections.forEach(section => {
                section.classList.remove('active');
                if (section.id === target) {
                    section.classList.add('active');
                }
            });

            // Scroll content area to top
            const contentArea = document.querySelector('.mxch-content');
            if (contentArea) {
                contentArea.scrollTop = 0;
            }
        }

        function setActiveNav(clickedLink) {
            // Remove active from all links
            navLinks.forEach(l => l.classList.remove('active'));

            // Add active to clicked link
            clickedLink.classList.add('active');

            // If clicking a sub-link, also highlight parent
            if (clickedLink.classList.contains('mxch-nav-sub-link')) {
                const parent = clickedLink.closest('.mxch-nav-item');
                if (parent) {
                    parent.querySelector('.mxch-nav-link').classList.add('active');
                }
            }
        }

        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const target = this.dataset.target;
                const parentItem = this.closest('.mxch-nav-item');
                const hasSubmenu = parentItem && parentItem.querySelector('.mxch-nav-sub');

                // If this is a parent nav link WITH children (expandable menu)
                if (this.classList.contains('mxch-nav-link') && hasSubmenu) {
                    const wasExpanded = parentItem.classList.contains('expanded');

                    // Collapse all other expandable items
                    navItems.forEach(item => {
                        if (item !== parentItem && item.querySelector('.mxch-nav-sub')) {
                            item.classList.remove('expanded');
                        }
                    });

                    // Toggle this item
                    parentItem.classList.toggle('expanded');

                    // If expanding, show the first sub-item's content and mark it active
                    if (!wasExpanded) {
                        const firstSubLink = parentItem.querySelector('.mxch-nav-sub-link');
                        if (firstSubLink) {
                            const firstTarget = firstSubLink.dataset.target;
                            showSection(firstTarget);
                            setActiveNav(firstSubLink);
                            // Also mark parent as active
                            this.classList.add('active');
                            history.replaceState(null, null, '#' + firstTarget);
                        }
                    }
                }
                // If this is a nav link WITHOUT children (like API Keys, Tutorials)
                else if (this.classList.contains('mxch-nav-link') && !hasSubmenu && target) {
                    // Collapse all expandable items
                    navItems.forEach(item => {
                        if (item.querySelector('.mxch-nav-sub')) {
                            item.classList.remove('expanded');
                        }
                    });

                    // Show the section
                    showSection(target);
                    setActiveNav(this);
                    history.replaceState(null, null, '#' + target);
                }
                // If this is a sub-link
                else if (this.classList.contains('mxch-nav-sub-link') && target) {
                    // Ensure parent stays expanded
                    if (parentItem) {
                        parentItem.classList.add('expanded');
                    }
                    showSection(target);
                    setActiveNav(this);
                    history.replaceState(null, null, '#' + target);
                }
            });
        });

        // Handle initial hash
        const hash = window.location.hash.slice(1);
        if (hash) {
            const targetLink = document.querySelector('[data-target="' + hash + '"]');
            if (targetLink) {
                // Expand parent if it's a sub-link
                const parentItem = targetLink.closest('.mxch-nav-item');
                if (parentItem && targetLink.classList.contains('mxch-nav-sub-link')) {
                    parentItem.classList.add('expanded');
                }
                showSection(hash);
                setActiveNav(targetLink);
            }
        }

        // Rate limit accordion
        const rateLimitHeaders = document.querySelectorAll('.mxch-rate-limit-header');
        rateLimitHeaders.forEach(header => {
            header.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const item = this.closest('.mxch-rate-limit-item');
                item.classList.toggle('expanded');
            });
        });

        // =====================================================
        // Mobile Menu Functionality
        // =====================================================
        const mobileMenuBtn = document.querySelector('.mxch-mobile-menu-btn');
        const mobileMenuClose = document.querySelector('.mxch-mobile-menu-close');
        const mobileMenu = document.querySelector('.mxch-mobile-menu');
        const mobileOverlay = document.querySelector('.mxch-mobile-overlay');
        const mobileNavLinks = document.querySelectorAll('.mxch-mobile-nav-link, .mxch-mobile-nav-sub-link');

        function openMobileMenu() {
            mobileMenu.classList.add('open');
            mobileOverlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileMenu() {
            mobileMenu.classList.remove('open');
            mobileOverlay.classList.remove('open');
            document.body.style.overflow = '';
        }

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', openMobileMenu);
        }

        if (mobileMenuClose) {
            mobileMenuClose.addEventListener('click', closeMobileMenu);
        }

        if (mobileOverlay) {
            mobileOverlay.addEventListener('click', closeMobileMenu);
        }

        // Mobile navigation links
        mobileNavLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const target = this.dataset.target;
                const parentId = this.dataset.parent;

                // If this is an expandable parent link
                if (parentId && !target) {
                    const subNav = document.querySelector('.mxch-mobile-nav-sub[data-parent="' + parentId + '"]');
                    if (subNav) {
                        const isExpanded = subNav.classList.contains('expanded');
                        // Collapse all sub navs
                        document.querySelectorAll('.mxch-mobile-nav-sub').forEach(nav => {
                            nav.classList.remove('expanded');
                        });
                        document.querySelectorAll('.mxch-mobile-nav-link').forEach(l => {
                            l.classList.remove('expanded');
                        });
                        // Toggle this one
                        if (!isExpanded) {
                            subNav.classList.add('expanded');
                            this.classList.add('expanded');
                        }
                    }
                }
                // If this is a direct link or sub-link with a target
                else if (target) {
                    // Update mobile nav active state
                    document.querySelectorAll('.mxch-mobile-nav-link, .mxch-mobile-nav-sub-link').forEach(l => {
                        l.classList.remove('active');
                    });
                    this.classList.add('active');

                    // Also update desktop sidebar nav
                    const desktopLink = document.querySelector('.mxch-sidebar [data-target="' + target + '"]');
                    if (desktopLink) {
                        setActiveNav(desktopLink);
                        const parentItem = desktopLink.closest('.mxch-nav-item');
                        if (parentItem && desktopLink.classList.contains('mxch-nav-sub-link')) {
                            parentItem.classList.add('expanded');
                        }
                    }

                    // Show section and close menu
                    showSection(target);
                    history.replaceState(null, null, '#' + target);
                    closeMobileMenu();
                }
            });
        });

        // Close mobile menu on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && mobileMenu && mobileMenu.classList.contains('open')) {
                closeMobileMenu();
            }
        });
    });
    </script>
    <?php
}

/**
 * Helper function to render a field with consistent wrapper
 */
function mxchat_render_field_wrapper($id, $label, $callback, $description = '') {
    ?>
    <div class="mxch-field">
        <label class="mxch-field-label"><?php echo esc_html($label); ?></label>
        <div class="mxch-field-control">
            <?php $callback(); ?>
        </div>
        <?php if ($description): ?>
        <p class="mxch-field-description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render rate limits as collapsible accordion
 */
function mxchat_render_rate_limits_accordion($admin_instance) {
    $all_options = get_option('mxchat_options', []);
    $rate_limits = array('1', '3', '5', '10', '15', '20', '50', '100', 'unlimited');
    $timeframes = array(
        'hourly' => __('Per Hour', 'mxchat'),
        'daily' => __('Per Day', 'mxchat'),
        'weekly' => __('Per Week', 'mxchat'),
        'monthly' => __('Per Month', 'mxchat')
    );

    $roles = wp_roles()->get_names();
    $roles['logged_out'] = __('Logged Out Users', 'mxchat');

    echo '<div class="mxch-rate-limits">';

    foreach ($roles as $role_id => $role_name) {
        $default_limit = ($role_id === 'logged_out') ? '10' : '100';
        $default_timeframe = 'daily';
        $default_message = __('Rate limit exceeded. Please try again later.', 'mxchat');

        $selected_limit = isset($all_options['rate_limits'][$role_id]['limit'])
            ? $all_options['rate_limits'][$role_id]['limit']
            : $default_limit;

        $selected_timeframe = isset($all_options['rate_limits'][$role_id]['timeframe'])
            ? $all_options['rate_limits'][$role_id]['timeframe']
            : $default_timeframe;

        $custom_message = isset($all_options['rate_limits'][$role_id]['message'])
            ? $all_options['rate_limits'][$role_id]['message']
            : $default_message;

        $timeframe_label = isset($timeframes[$selected_timeframe]) ? $timeframes[$selected_timeframe] : $selected_timeframe;
        ?>
        <div class="mxch-rate-limit-item">
            <div class="mxch-rate-limit-header">
                <span class="mxch-rate-limit-role"><?php echo esc_html($role_name); ?></span>
                <div class="mxch-rate-limit-summary">
                    <span class="mxch-rate-limit-badge"><?php echo esc_html($selected_limit . ' / ' . $timeframe_label); ?></span>
                    <svg class="mxch-rate-limit-arrow" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
            </div>
            <div class="mxch-rate-limit-body mxchat-autosave-section">
                <div class="mxch-rate-limit-controls">
                    <div class="mxch-field">
                        <label class="mxch-field-label" for="rate_limits_<?php echo esc_attr($role_id); ?>_limit"><?php esc_html_e('Limit', 'mxchat'); ?></label>
                        <select id="rate_limits_<?php echo esc_attr($role_id); ?>_limit"
                                name="mxchat_options[rate_limits][<?php echo esc_attr($role_id); ?>][limit]"
                                class="mxch-select mxchat-autosave-field">
                            <?php foreach ($rate_limits as $limit): ?>
                            <option value="<?php echo esc_attr($limit); ?>" <?php selected($selected_limit, $limit); ?>><?php echo esc_html($limit); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mxch-field">
                        <label class="mxch-field-label" for="rate_limits_<?php echo esc_attr($role_id); ?>_timeframe"><?php esc_html_e('Timeframe', 'mxchat'); ?></label>
                        <select id="rate_limits_<?php echo esc_attr($role_id); ?>_timeframe"
                                name="mxchat_options[rate_limits][<?php echo esc_attr($role_id); ?>][timeframe]"
                                class="mxch-select mxchat-autosave-field">
                            <?php foreach ($timeframes as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($selected_timeframe, $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mxch-field">
                    <label class="mxch-field-label" for="rate_limits_<?php echo esc_attr($role_id); ?>_message"><?php esc_html_e('Custom Message', 'mxchat'); ?></label>
                    <textarea id="rate_limits_<?php echo esc_attr($role_id); ?>_message"
                              name="mxchat_options[rate_limits][<?php echo esc_attr($role_id); ?>][message]"
                              class="mxch-textarea mxchat-autosave-field"
                              rows="2"
                              placeholder="<?php esc_attr_e('Message shown when rate limit is reached', 'mxchat'); ?>"><?php echo esc_textarea($custom_message); ?></textarea>
                </div>
            </div>
        </div>
        <?php
    }

    echo '</div>';
}
