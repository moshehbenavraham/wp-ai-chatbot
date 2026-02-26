<?php
/**
 * MxChat Knowledge Base Page - Redesigned with Sidebar Navigation
 *
 * @package MxChat
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the new sidebar-based Knowledge Base page
 */
function mxchat_render_knowledge_page($admin_instance, $knowledge_manager, $page_data) {
    $is_activated = $admin_instance->is_activated();
    $plugin_url = plugin_dir_url(dirname(__FILE__));

    // Check if integrations are active
    $pinecone_options = get_option('mxchat_pinecone_addon_options', array());
    $is_pinecone_active = ($pinecone_options['mxchat_use_pinecone'] ?? '0') === '1';

    $vectorstore_options = get_option('mxchat_openai_vectorstore_options', array());
    $is_vectorstore_active = ($vectorstore_options['mxchat_use_openai_vectorstore'] ?? '0') === '1';

    // Extract page data
    extract($page_data);
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
                <span class="mxch-mobile-menu-title"><?php esc_html_e('Knowledge Base', 'mxchat'); ?></span>
                <button type="button" class="mxch-mobile-menu-close" aria-label="<?php esc_attr_e('Close menu', 'mxchat'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <nav class="mxch-mobile-menu-nav">
                <!-- Import Section -->
                <div class="mxch-mobile-nav-section">
                    <div class="mxch-mobile-nav-section-title"><?php esc_html_e('Import', 'mxchat'); ?></div>
                    <button class="mxch-mobile-nav-link active" data-target="import-options">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <span><?php esc_html_e('Import Options', 'mxchat'); ?></span>
                    </button>
                    <button class="mxch-mobile-nav-link" data-target="knowledge-base">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                        <span><?php esc_html_e('Knowledge Base', 'mxchat'); ?></span>
                    </button>
                </div>
                <!-- Settings Section -->
                <div class="mxch-mobile-nav-section">
                    <div class="mxch-mobile-nav-section-title"><?php esc_html_e('Settings', 'mxchat'); ?></div>
                    <button class="mxch-mobile-nav-link" data-target="auto-sync">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
                        <span><?php esc_html_e('Auto-Sync', 'mxchat'); ?></span>
                    </button>
                    <button class="mxch-mobile-nav-link" data-target="chunking">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        <span><?php esc_html_e('Content Chunking', 'mxchat'); ?></span>
                    </button>
                    <button class="mxch-mobile-nav-link" data-target="role-restrictions">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span><?php esc_html_e('Role Restrictions', 'mxchat'); ?></span>
                    </button>
                    <button class="mxch-mobile-nav-link" data-target="acf-fields">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
                        <span><?php esc_html_e('ACF Fields', 'mxchat'); ?></span>
                    </button>
                    <button class="mxch-mobile-nav-link" data-target="custom-meta">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7V4h16v3"/><path d="M9 20h6"/><path d="M12 4v16"/></svg>
                        <span><?php esc_html_e('Custom Meta', 'mxchat'); ?></span>
                    </button>
                </div>
                <!-- Integrations Section -->
                <div class="mxch-mobile-nav-section">
                    <div class="mxch-mobile-nav-section-title"><?php esc_html_e('Integrations', 'mxchat'); ?></div>
                    <button class="mxch-mobile-nav-link" data-target="pinecone">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>
                        <span><?php esc_html_e('Pinecone', 'mxchat'); ?></span>
                        <?php if ($is_pinecone_active): ?>
                        <span class="mxch-nav-link-badge mxch-active-badge"><?php esc_html_e('Active', 'mxchat'); ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="mxch-mobile-nav-link" data-target="openai-vectorstore">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M11 8v6"/><path d="M8 11h6"/></svg>
                        <span><?php esc_html_e('OpenAI Vector Store', 'mxchat'); ?></span>
                        <?php if ($is_vectorstore_active): ?>
                        <span class="mxch-nav-link-badge mxch-active-badge"><?php esc_html_e('Active', 'mxchat'); ?></span>
                        <?php endif; ?>
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
                <!-- Import Section -->
                <div class="mxch-nav-section">
                    <div class="mxch-nav-section-title"><?php esc_html_e('Import', 'mxchat'); ?></div>

                    <div class="mxch-nav-item" data-section="import-options">
                        <button class="mxch-nav-link active" data-target="import-options">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('Import Options', 'mxchat'); ?></span>
                        </button>
                    </div>

                    <div class="mxch-nav-item" data-section="knowledge-base">
                        <button class="mxch-nav-link" data-target="knowledge-base">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('Knowledge Base', 'mxchat'); ?></span>
                            <span id="mxchat-sidebar-count" class="mxch-nav-link-badge"><?php echo esc_html($total_records); ?></span>
                        </button>
                    </div>
                </div>

                <!-- Settings Section -->
                <div class="mxch-nav-section">
                    <div class="mxch-nav-section-title"><?php esc_html_e('Settings', 'mxchat'); ?></div>

                    <div class="mxch-nav-item" data-section="auto-sync">
                        <button class="mxch-nav-link" data-target="auto-sync">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('Auto-Sync', 'mxchat'); ?></span>
                        </button>
                    </div>

                    <div class="mxch-nav-item" data-section="chunking">
                        <button class="mxch-nav-link" data-target="chunking">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('Content Chunking', 'mxchat'); ?></span>
                        </button>
                    </div>

                    <div class="mxch-nav-item" data-section="role-restrictions">
                        <button class="mxch-nav-link" data-target="role-restrictions">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('Role Restrictions', 'mxchat'); ?></span>
                        </button>
                    </div>

                    <div class="mxch-nav-item" data-section="acf-fields">
                        <button class="mxch-nav-link" data-target="acf-fields">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('ACF Fields', 'mxchat'); ?></span>
                        </button>
                    </div>

                    <div class="mxch-nav-item" data-section="custom-meta">
                        <button class="mxch-nav-link" data-target="custom-meta">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7V4h16v3"/><path d="M9 20h6"/><path d="M12 4v16"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('Custom Meta', 'mxchat'); ?></span>
                        </button>
                    </div>
                </div>

                <!-- Integrations Section -->
                <div class="mxch-nav-section">
                    <div class="mxch-nav-section-title"><?php esc_html_e('Integrations', 'mxchat'); ?></div>

                    <div class="mxch-nav-item" data-section="pinecone">
                        <button class="mxch-nav-link" data-target="pinecone">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('Pinecone', 'mxchat'); ?></span>
                            <?php if ($is_pinecone_active): ?>
                            <span class="mxch-nav-link-badge mxch-active-badge"><?php esc_html_e('Active', 'mxchat'); ?></span>
                            <?php endif; ?>
                        </button>
                    </div>

                    <div class="mxch-nav-item" data-section="openai-vectorstore">
                        <button class="mxch-nav-link" data-target="openai-vectorstore">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/><path d="M11 8v6"/><path d="M8 11h6"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('OpenAI Vector Store', 'mxchat'); ?></span>
                            <?php if ($is_vectorstore_active): ?>
                            <span class="mxch-nav-link-badge mxch-active-badge"><?php esc_html_e('Active', 'mxchat'); ?></span>
                            <?php endif; ?>
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
            <?php
            // Multi-Bot Selector
            mxchat_render_knowledge_bot_selector($multibot_active, $current_bot_id);

            // Global Processing Status - Always visible when processing
            mxchat_render_global_processing_status($page_data, $knowledge_manager);

            // Render content sections
            mxchat_render_import_options_section($admin_instance, $knowledge_manager, $page_data);
            mxchat_render_knowledge_base_section($admin_instance, $knowledge_manager, $page_data);
            mxchat_render_auto_sync_section($knowledge_manager);
            mxchat_render_chunking_section();
            mxchat_render_role_restrictions_section($knowledge_manager);
            mxchat_render_acf_fields_section($knowledge_manager);
            mxchat_render_custom_meta_section();
            mxchat_render_pinecone_section();
            mxchat_render_openai_vectorstore_section();
            ?>

        </main>
    </div>

    <?php
    // Render the Content Selector Modal
    mxchat_render_content_selector_modal();

    // Render the navigation JavaScript
    mxchat_render_knowledge_page_scripts();
}

/**
 * Render Multi-Bot Selector
 */
function mxchat_render_knowledge_bot_selector($multibot_active, $current_bot_id) {
    if (!$multibot_active || !class_exists('MxChat_Multi_Bot_Manager')) {
        return;
    }

    $multi_bot_manager = MxChat_Multi_Bot_Core_Manager::get_instance();
    $available_bots = $multi_bot_manager->get_available_bots();
    ?>
    <div class="mxch-card" style="margin-bottom: 24px;">
        <div class="mxch-card-body">
            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                <label for="mxchat-bot-selector" style="font-weight: 600; white-space: nowrap;">
                    <?php esc_html_e('Select Bot Database:', 'mxchat'); ?>
                </label>
                <select id="mxchat-bot-selector" class="mxch-select" style="min-width: 200px; max-width: 300px;">
                    <?php foreach ($available_bots as $bot_id => $bot_name) : ?>
                        <option value="<?php echo esc_attr($bot_id); ?>" <?php selected($current_bot_id, $bot_id); ?>>
                            <?php echo esc_html($bot_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span style="color: var(--mxch-text-secondary); font-size: 13px;">
                    <?php esc_html_e('Content will be added to the selected bot\'s knowledge base', 'mxchat'); ?>
                </span>
                <span id="mxchat-bot-save-status" style="display: none; color: var(--mxch-success); font-size: 13px;">
                    ✓ <?php esc_html_e('Saved', 'mxchat'); ?>
                </span>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render Global Processing Status - Always visible when processing is active
 */
function mxchat_render_global_processing_status($page_data, $knowledge_manager) {
    extract($page_data);

    // Only show if there's active processing
    if (!$is_processing) {
        return;
    }
    ?>
    <div class="mxch-processing-status-global mxchat-import-section" style="margin-bottom: 24px;">
        <?php
        // PDF Processing Status - Using mxchat-status-card class for JavaScript compatibility
        if ($pdf_status && $pdf_status['status'] === 'processing') : ?>
            <div class="mxchat-status-card mxch-card" data-card-type="pdf" data-queue-id="<?php echo esc_attr(get_transient('mxchat_active_queue_pdf')); ?>" style="border-left: 4px solid var(--mxch-primary); margin-bottom: 16px;">
                <div class="mxchat-status-header mxch-card-header" style="background: var(--mxch-primary-light);">
                    <h4 style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <?php esc_html_e('PDF Processing Status', 'mxchat'); ?>
                    </h4>
                    <div class="mxchat-status-warning" style="background: #fff3cd; color: #856404; padding: 8px 12px; border-radius: 4px; font-size: 13px; margin: 10px 0;">
                        <?php esc_html_e('Keep this tab open - Processing runs in your browser', 'mxchat'); ?>
                    </div>
                    <form method="post" class="mxchat-stop-form" action="<?php echo esc_url(admin_url('admin-post.php?action=mxchat_stop_processing')); ?>">
                        <?php wp_nonce_field('mxchat_stop_processing_action', 'mxchat_stop_processing_nonce'); ?>
                        <button type="submit" name="stop_processing" class="mxchat-button-secondary mxch-btn mxch-btn-secondary mxch-btn-sm">
                            <?php esc_html_e('Stop Processing', 'mxchat'); ?>
                        </button>
                    </form>
                </div>
                <div class="mxchat-progress-bar" style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin: 0;">
                    <div class="mxchat-progress-fill" style="height: 100%; background: var(--mxch-primary); width: <?php echo esc_attr($pdf_status['percentage']); ?>%; transition: width 0.3s;"></div>
                </div>
                <div class="mxchat-status-details mxch-card-body">
                    <p><?php printf(esc_html__('Progress: %1$d of %2$d pages (%3$d%%)', 'mxchat'), absint($pdf_status['processed_pages']), absint($pdf_status['total_pages']), absint($pdf_status['percentage'])); ?></p>
                    <?php if (!empty($pdf_status['failed_pages']) && $pdf_status['failed_pages'] > 0) : ?>
                        <p class="error-count" style="color: var(--mxch-error);"><strong><?php esc_html_e('Failed pages:', 'mxchat'); ?></strong> <?php echo esc_html($pdf_status['failed_pages']); ?></p>
                    <?php endif; ?>
                    <p><strong><?php esc_html_e('Status:', 'mxchat'); ?></strong> <?php esc_html_e('Processing', 'mxchat'); ?></p>
                </div>
            </div>
        <?php endif;

        // Sitemap Processing Status - Using mxchat-status-card class for JavaScript compatibility
        if ($sitemap_status && $sitemap_status['status'] === 'processing') : ?>
            <div class="mxchat-status-card mxch-card" data-card-type="sitemap" data-queue-id="<?php echo esc_attr(get_transient('mxchat_active_queue_sitemap')); ?>" style="border-left: 4px solid var(--mxch-primary); margin-bottom: 16px;">
                <div class="mxchat-status-header mxch-card-header" style="background: var(--mxch-primary-light);">
                    <h4 style="margin: 0; display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        <?php esc_html_e('Sitemap Processing Status', 'mxchat'); ?>
                    </h4>
                    <div class="mxchat-status-warning" style="background: #fff3cd; color: #856404; padding: 8px 12px; border-radius: 4px; font-size: 13px; margin: 10px 0;">
                        <?php esc_html_e('Keep this tab open - Processing runs in your browser', 'mxchat'); ?>
                    </div>
                    <form method="post" class="mxchat-stop-form" action="<?php echo esc_url(admin_url('admin-post.php?action=mxchat_stop_processing')); ?>">
                        <?php wp_nonce_field('mxchat_stop_processing_action', 'mxchat_stop_processing_nonce'); ?>
                        <button type="submit" name="stop_processing" class="mxchat-button-secondary mxch-btn mxch-btn-secondary mxch-btn-sm">
                            <?php esc_html_e('Stop Processing', 'mxchat'); ?>
                        </button>
                    </form>
                </div>
                <div class="mxchat-progress-bar" style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin: 0;">
                    <div class="mxchat-progress-fill" style="height: 100%; background: var(--mxch-primary); width: <?php echo esc_attr($sitemap_status['percentage']); ?>%; transition: width 0.3s;"></div>
                </div>
                <div class="mxchat-status-details mxch-card-body">
                    <p><?php printf(esc_html__('Progress: %1$d of %2$d URLs (%3$d%%)', 'mxchat'), absint($sitemap_status['processed_urls']), absint($sitemap_status['total_urls']), absint($sitemap_status['percentage'])); ?></p>
                    <?php if (!empty($sitemap_status['failed_urls']) && $sitemap_status['failed_urls'] > 0) : ?>
                        <p class="error-count" style="color: var(--mxch-error);"><strong><?php esc_html_e('Failed URLs:', 'mxchat'); ?></strong> <?php echo esc_html($sitemap_status['failed_urls']); ?></p>
                    <?php endif; ?>
                    <p><strong><?php esc_html_e('Status:', 'mxchat'); ?></strong> <?php esc_html_e('Processing', 'mxchat'); ?></p>
                </div>
            </div>
        <?php endif;

        // Completed status cards
        echo $knowledge_manager->mxchat_render_completed_status_cards();
        ?>
    </div>
    <?php
}

/**
 * Render Import Options Section
 */
function mxchat_render_import_options_section($admin_instance, $knowledge_manager, $page_data) {
    extract($page_data);
    $options = $admin_instance->options ?? get_option('mxchat_options', array());

    // Check if user is using WordPress database (not Pinecone or OpenAI Vector Store)
    $pinecone_options = get_option('mxchat_pinecone_addon_options', array());
    $is_pinecone_active = ($pinecone_options['mxchat_use_pinecone'] ?? '0') === '1';
    $vectorstore_options = get_option('mxchat_openai_vectorstore_options', array());
    $is_vectorstore_active = ($vectorstore_options['mxchat_use_openai_vectorstore'] ?? '0') === '1';
    $is_using_wordpress_db = !$is_pinecone_active && !$is_vectorstore_active;

    // Check embedding API key
    $embedding_model = isset($options['embedding_model']) ? esc_attr($options['embedding_model']) : 'text-embedding-ada-002';
    $has_openai_key = !empty($options['api_key']);
    $has_voyage_key = !empty($options['voyage_api_key']);
    $has_gemini_key = !empty($options['gemini_api_key']);

    $has_required_key = false;
    $required_key_type = '';

    if (strpos($embedding_model, 'text-embedding-') !== false && $has_openai_key) {
        $has_required_key = true;
        $required_key_type = 'OpenAI';
    } elseif (strpos($embedding_model, 'voyage-') !== false && $has_voyage_key) {
        $has_required_key = true;
        $required_key_type = 'Voyage AI';
    } elseif (strpos($embedding_model, 'gemini-embedding-') !== false && $has_gemini_key) {
        $has_required_key = true;
        $required_key_type = 'Google Gemini';
    } elseif (strpos($embedding_model, 'text-embedding-') !== false) {
        $required_key_type = 'OpenAI';
    } elseif (strpos($embedding_model, 'voyage-') !== false) {
        $required_key_type = 'Voyage AI';
    } elseif (strpos($embedding_model, 'gemini-embedding-') !== false) {
        $required_key_type = 'Google Gemini';
    }
    ?>
    <div id="import-options" class="mxch-section active">
        <div class="mxch-content-header">
            <h1 class="mxch-content-title"><?php esc_html_e('Import Options', 'mxchat'); ?></h1>
            <p class="mxch-content-subtitle"><?php esc_html_e('Import content to your knowledge base from various sources.', 'mxchat'); ?></p>
        </div>

        <!-- API Key Status -->
        <div class="mxch-notice <?php echo $has_required_key ? 'mxch-notice-success' : 'mxch-notice-warning'; ?>" style="margin-bottom: 24px;">
            <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <?php if ($has_required_key): ?>
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                <?php else: ?>
                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                <?php endif; ?>
            </svg>
            <div>
                <?php if ($has_required_key): ?>
                    <?php echo wp_kses_post(sprintf(__('We detected your %s API key. <strong>Remember to add credits to your %s account</strong> before using the knowledgebase.', 'mxchat'), $required_key_type, $required_key_type)); ?>
                <?php else: ?>
                    <strong><?php esc_html_e('Important:', 'mxchat'); ?></strong>
                    <?php echo sprintf(esc_html__('Before importing knowledge, you must add a %s API key with sufficient credits in the Chatbot settings.', 'mxchat'), $required_key_type); ?>
                    <a href="<?php echo admin_url('admin.php?page=mxchat-max#api-keys'); ?>"><?php esc_html_e('Go to API Key Settings', 'mxchat'); ?></a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($is_using_wordpress_db): ?>
        <!-- WordPress Database Caution Notice -->
        <div class="mxch-notice mxch-notice-warning" style="margin-bottom: 24px;">
            <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
            <div>
                <strong><?php esc_html_e('Using WordPress Database:', 'mxchat'); ?></strong>
                <?php esc_html_e('The WordPress database is not optimized for vector search with large datasets. If you plan to have more than 500 knowledge entries, we highly recommend using Pinecone for better performance and scalability.', 'mxchat'); ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="mxch-card">
            <div class="mxch-card-header">
                <h3 class="mxch-card-title">
                    <svg class="mxch-card-title-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <?php esc_html_e('Choose Import Method', 'mxchat'); ?>
                </h3>
            </div>
            <div class="mxch-card-body">
                <!-- Import Options Grid -->
                <div class="mxchat-import-options">
                    <!-- WordPress Import Option -->
                    <button type="button" id="mxchat-open-content-selector" class="mxchat-import-box mxchat-import-wordpress" data-option="wordpress">
                        <div class="mxchat-import-icon">
                            <span class="dashicons dashicons-wordpress"></span>
                        </div>
                        <div class="mxchat-import-content">
                            <h4><?php esc_html_e('WordPress Content', 'mxchat'); ?></h4>
                            <p><?php esc_html_e('Import specific posts and pages to your knowledge base.', 'mxchat'); ?></p>
                        </div>
                        <div class="mxchat-recommended-tag"><?php esc_html_e('Recommended', 'mxchat'); ?></div>
                    </button>

                    <!-- Sitemap Import Option -->
                    <button type="button" class="mxchat-import-box" data-option="sitemap" data-placeholder="<?php esc_attr_e('Enter sitemap URL here', 'mxchat'); ?>" data-type="sitemap">
                        <div class="mxchat-import-icon">
                            <span class="dashicons dashicons-admin-site-alt"></span>
                        </div>
                        <div class="mxchat-import-content">
                            <h4><?php esc_html_e('Sitemap Import', 'mxchat'); ?></h4>
                            <p><?php esc_html_e('Use a content-specific sub-sitemap, not the sitemap index.', 'mxchat'); ?></p>
                        </div>
                    </button>

                    <!-- Direct URL Import Option -->
                    <button type="button" class="mxchat-import-box" data-option="url" data-placeholder="<?php esc_attr_e('Enter webpage URL here', 'mxchat'); ?>" data-type="url">
                        <div class="mxchat-import-icon">
                            <span class="dashicons dashicons-admin-links"></span>
                        </div>
                        <div class="mxchat-import-content">
                            <h4><?php esc_html_e('Direct URL', 'mxchat'); ?></h4>
                            <p><?php esc_html_e('Import content from any webpage.', 'mxchat'); ?></p>
                        </div>
                    </button>

                    <!-- Direct Content Import Option -->
                    <button type="button" class="mxchat-import-box" data-option="content" data-type="content">
                        <div class="mxchat-import-icon">
                            <span class="dashicons dashicons-editor-paste-text"></span>
                        </div>
                        <div class="mxchat-import-content">
                            <h4><?php esc_html_e('Direct Content', 'mxchat'); ?></h4>
                            <p><?php esc_html_e('Submit content to be vectorized.', 'mxchat'); ?></p>
                        </div>
                    </button>

                    <!-- PDF Import Option -->
                    <button type="button" class="mxchat-import-box" data-option="pdf" data-placeholder="<?php esc_attr_e('Enter PDF URL here', 'mxchat'); ?>" data-type="pdf">
                        <div class="mxchat-import-icon">
                            <span class="dashicons dashicons-media-document"></span>
                        </div>
                        <div class="mxchat-import-content">
                            <h4><?php esc_html_e('PDF Import', 'mxchat'); ?></h4>
                            <p><?php esc_html_e('Import knowledge from PDF documents.', 'mxchat'); ?></p>
                        </div>
                    </button>
                </div>

                <!-- Detected Sitemaps Section (hidden by default, shown when Sitemap Import is clicked) -->
                <div id="mxchat-detected-sitemaps" class="mxchat-detected-sitemaps" style="display: none;">
                    <div class="mxchat-sitemaps-header">
                        <h4>
                            <span class="dashicons dashicons-admin-site-alt"></span>
                            <?php esc_html_e('Detected Sitemaps', 'mxchat'); ?>
                        </h4>
                        <button type="button" id="mxchat-refresh-sitemaps" class="mxch-btn mxch-btn-ghost mxch-btn-sm" title="<?php esc_attr_e('Refresh', 'mxchat'); ?>">
                            <span class="dashicons dashicons-update"></span>
                        </button>
                    </div>
                    <div id="mxchat-sitemaps-list" class="mxchat-sitemaps-list">
                        <!-- Sitemaps will be loaded here via AJAX -->
                    </div>
                    <input type="hidden" id="mxchat-detect-sitemaps-nonce" value="<?php echo wp_create_nonce('mxchat_detect_sitemaps_nonce'); ?>">
                    <?php if ($multibot_active && $current_bot_id !== 'default') : ?>
                        <input type="hidden" id="mxchat-sitemap-bot-id" value="<?php echo esc_attr($current_bot_id); ?>">
                    <?php endif; ?>
                </div>

                <!-- No Sitemaps Found Message (hidden by default) -->
                <div id="mxchat-no-sitemaps" class="mxchat-no-sitemaps" style="display: none;">
                    <span class="dashicons dashicons-info"></span>
                    <p>
                        <?php esc_html_e('No sitemaps detected. You can manually enter a sitemap URL using the input field above.', 'mxchat'); ?>
                    </p>
                </div>

                <!-- Sitemaps Loading State (hidden by default) -->
                <div id="mxchat-sitemaps-loading" class="mxchat-sitemaps-loading" style="display: none;">
                    <span class="dashicons dashicons-update spin"></span>
                    <p><?php esc_html_e('Detecting sitemaps...', 'mxchat'); ?></p>
                </div>

                <!-- Input Areas for URL and Content -->
                <?php if (!$is_processing) : ?>
                <div class="mxchat-import-input-area" id="mxchat-url-input-area" style="display: none; margin-top: 20px;">
                    <form id="mxchat-url-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=mxchat_submit_sitemap')); ?>">
                        <?php wp_nonce_field('mxchat_submit_sitemap_action', 'mxchat_submit_sitemap_nonce'); ?>
                        <input type="hidden" name="import_type" id="import_type" value="url">
                        <?php if ($multibot_active && $current_bot_id !== 'default') : ?>
                            <input type="hidden" name="bot_id" value="<?php echo esc_attr($current_bot_id); ?>">
                        <?php endif; ?>
                        <div style="display: flex; gap: 10px;">
                            <input type="url" name="sitemap_url" id="sitemap_url" class="mxch-input" placeholder="<?php esc_attr_e('Enter URL here', 'mxchat'); ?>" required style="flex: 1;" />
                            <button type="submit" name="submit_sitemap" class="mxch-btn mxch-btn-primary">
                                <?php esc_html_e('Import', 'mxchat'); ?>
                            </button>
                        </div>
                        <p class="mxch-field-description" id="url-description-text"></p>
                    </form>
                </div>

                <div class="mxchat-import-input-area" id="mxchat-content-input-area" style="display: none; margin-top: 20px;">
                    <form id="mxchat-content-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=mxchat_submit_content')); ?>">
                        <?php wp_nonce_field('mxchat_submit_content_action', 'mxchat_submit_content_nonce'); ?>
                        <?php if ($multibot_active && $current_bot_id !== 'default') : ?>
                            <input type="hidden" name="bot_id" value="<?php echo esc_attr($current_bot_id); ?>">
                        <?php endif; ?>
                        <div class="mxch-field">
                            <textarea name="article_content" id="article_content" class="mxch-textarea" placeholder="<?php esc_attr_e('Enter your content here...', 'mxchat'); ?>" required rows="6"></textarea>
                        </div>
                        <div class="mxch-field">
                            <input type="url" name="article_url" id="article_url" class="mxch-input" placeholder="<?php esc_attr_e('Enter source URL (Optional)', 'mxchat'); ?>">
                            <div style="margin-top: 8px; display: flex; align-items: center; gap: 8px;">
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--mxch-text-secondary); cursor: pointer;">
                                    <input type="checkbox" id="mxchat-unique-url-toggle" style="margin: 0;">
                                    <?php esc_html_e('Generate unique URL for duplicate content', 'mxchat'); ?>
                                </label>
                                <button type="button" id="mxchat-generate-unique-url" class="mxch-btn mxch-btn-secondary mxch-btn-sm" style="display: none;">
                                    <span class="dashicons dashicons-randomize" style="font-size: 14px; margin-top: 2px;"></span>
                                    <?php esc_html_e('Generate Unique', 'mxchat'); ?>
                                </button>
                            </div>
                            <p class="mxch-field-description">
                                <?php esc_html_e('Enable this option to submit multiple entries with the same base URL. A unique reference will be appended.', 'mxchat'); ?>
                            </p>
                        </div>
                        <button type="submit" name="submit_content" class="mxch-btn mxch-btn-primary">
                            <?php esc_html_e('Import Content', 'mxchat'); ?>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render Processing Status Cards (legacy - kept for reference)
 * Note: Processing status is now rendered globally via mxchat_render_global_processing_status()
 */
function mxchat_render_processing_status($page_data, $knowledge_manager) {
    extract($page_data);

    // PDF Processing Status
    if ($pdf_status && $pdf_status['status'] === 'processing') : ?>
        <div class="mxch-card" style="margin-top: 20px;">
            <div class="mxch-card-header" style="background: var(--mxch-primary-light);">
                <h3 class="mxch-card-title"><?php esc_html_e('PDF Processing Status', 'mxchat'); ?></h3>
                <div style="display: flex; gap: 10px;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=mxchat_stop_processing')); ?>">
                        <?php wp_nonce_field('mxchat_stop_processing_action', 'mxchat_stop_processing_nonce'); ?>
                        <button type="submit" name="stop_processing" class="mxch-btn mxch-btn-secondary mxch-btn-sm">
                            <?php esc_html_e('Stop Processing', 'mxchat'); ?>
                        </button>
                    </form>
                    <button type="button" class="mxch-btn mxch-btn-primary mxch-btn-sm mxchat-manual-batch-btn" data-process-type="pdf" data-url="<?php echo esc_attr(get_transient('mxchat_last_pdf_url')); ?>">
                        <?php esc_html_e('Process Batch', 'mxchat'); ?>
                    </button>
                </div>
            </div>
            <div class="mxch-card-body">
                <div class="mxchat-progress-bar" style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin-bottom: 15px;">
                    <div class="mxchat-progress-fill" style="height: 100%; background: var(--mxch-primary); width: <?php echo esc_attr($pdf_status['percentage']); ?>%; transition: width 0.3s;"></div>
                </div>
                <p><?php printf(esc_html__('Progress: %1$d of %2$d pages (%3$d%%)', 'mxchat'), absint($pdf_status['processed_pages']), absint($pdf_status['total_pages']), absint($pdf_status['percentage'])); ?></p>
                <?php if (!empty($pdf_status['failed_pages']) && $pdf_status['failed_pages'] > 0) : ?>
                    <p><strong><?php esc_html_e('Failed pages:', 'mxchat'); ?></strong> <?php echo esc_html($pdf_status['failed_pages']); ?></p>
                <?php endif; ?>
                <p><strong><?php esc_html_e('Last update:', 'mxchat'); ?></strong> <?php echo esc_html($pdf_status['last_update']); ?></p>
            </div>
        </div>
    <?php endif;

    // Sitemap Processing Status
    if ($sitemap_status && $sitemap_status['status'] === 'processing') : ?>
        <div class="mxch-card" style="margin-top: 20px;">
            <div class="mxch-card-header" style="background: var(--mxch-primary-light);">
                <h3 class="mxch-card-title"><?php esc_html_e('Sitemap Processing Status', 'mxchat'); ?></h3>
                <div style="display: flex; gap: 10px;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=mxchat_stop_processing')); ?>">
                        <?php wp_nonce_field('mxchat_stop_processing_action', 'mxchat_stop_processing_nonce'); ?>
                        <button type="submit" name="stop_processing" class="mxch-btn mxch-btn-secondary mxch-btn-sm">
                            <?php esc_html_e('Stop Processing', 'mxchat'); ?>
                        </button>
                    </form>
                    <button type="button" class="mxch-btn mxch-btn-primary mxch-btn-sm mxchat-manual-batch-btn" data-process-type="sitemap" data-url="<?php echo esc_attr(get_transient('mxchat_last_sitemap_url')); ?>">
                        <?php esc_html_e('Process Batch', 'mxchat'); ?>
                    </button>
                </div>
            </div>
            <div class="mxch-card-body">
                <div class="mxchat-progress-bar" style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin-bottom: 15px;">
                    <div class="mxchat-progress-fill" style="height: 100%; background: var(--mxch-primary); width: <?php echo esc_attr($sitemap_status['percentage']); ?>%; transition: width 0.3s;"></div>
                </div>
                <p><?php printf(esc_html__('Progress: %1$d of %2$d URLs (%3$d%%)', 'mxchat'), absint($sitemap_status['processed_urls']), absint($sitemap_status['total_urls']), absint($sitemap_status['percentage'])); ?></p>
                <?php if (!empty($sitemap_status['failed_urls']) && $sitemap_status['failed_urls'] > 0) : ?>
                    <p><strong><?php esc_html_e('Failed URLs:', 'mxchat'); ?></strong> <?php echo esc_html($sitemap_status['failed_urls']); ?></p>
                <?php endif; ?>
                <p><strong><?php esc_html_e('Last update:', 'mxchat'); ?></strong> <?php echo esc_html($sitemap_status['last_update']); ?></p>
            </div>
        </div>
    <?php endif;

    // Completed status cards
    echo $knowledge_manager->mxchat_render_completed_status_cards();
}

/**
 * Render Knowledge Base Section (table)
 */
function mxchat_render_knowledge_base_section($admin_instance, $knowledge_manager, $page_data) {
    extract($page_data);

    // Group prompts by source_url for chunked content display
    $grouped_prompts = array();

    if ($prompts) {
        foreach ($prompts as $prompt) {
            $source_url = $prompt->source_url ?? '';

            // Check if chunk metadata exists on the record itself (Pinecone)
            if (isset($prompt->chunk_index) && $prompt->chunk_index !== null) {
                // Pinecone: metadata is already on the record object
                $prompt->chunk_metadata = array(
                    'chunk_index' => intval($prompt->chunk_index),
                    'total_chunks' => isset($prompt->total_chunks) ? intval($prompt->total_chunks) : null,
                    'is_chunked' => isset($prompt->is_chunked) ? (bool) $prompt->is_chunked : true
                );
                $prompt->display_content = $prompt->article_content; // Pinecone content is already clean
            } else {
                // WordPress: Parse chunk metadata from JSON prefix in article_content
                if (class_exists('MxChat_Chunker')) {
                    $chunk_meta = MxChat_Chunker::parse_stored_chunk($prompt->article_content);
                    $prompt->chunk_metadata = $chunk_meta['metadata'];
                    $prompt->display_content = $chunk_meta['text']; // Store clean content without JSON prefix
                } else {
                    $prompt->chunk_metadata = array();
                    $prompt->display_content = $prompt->article_content;
                }
            }

            if (!empty($source_url)) {
                if (!isset($grouped_prompts[$source_url])) {
                    $grouped_prompts[$source_url] = array();
                }
                $grouped_prompts[$source_url][] = $prompt;
            } else {
                // Use a unique key for ungrouped prompts
                $grouped_prompts['_ungrouped_' . $prompt->id] = array($prompt);
            }
        }

        // Sort each group by chunk_index from metadata
        foreach ($grouped_prompts as $source_url => &$group) {
            usort($group, function($a, $b) {
                $index_a = isset($a->chunk_metadata['chunk_index']) ? intval($a->chunk_metadata['chunk_index']) : 0;
                $index_b = isset($b->chunk_metadata['chunk_index']) ? intval($b->chunk_metadata['chunk_index']) : 0;
                return $index_a - $index_b;
            });
        }
        unset($group); // Break reference
    }
    $display_index = 0;
    ?>
    <div id="knowledge-base" class="mxch-section">
        <div class="mxch-content-header">
            <h1 class="mxch-content-title"><?php esc_html_e('Knowledge Base', 'mxchat'); ?></h1>
            <p class="mxch-content-subtitle"><?php esc_html_e('View and manage your imported knowledge entries.', 'mxchat'); ?></p>
        </div>

        <?php if (!empty($use_vectorstore)) : ?>
        <div class="mxch-notice mxch-notice-info" style="margin-bottom: 20px;">
            <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <span><strong><?php esc_html_e('OpenAI Vector Store is Active', 'mxchat'); ?></strong> - <?php esc_html_e('Your chatbot is using OpenAI\'s hosted Vector Store for knowledge retrieval. The entries below are stored locally and are not being searched. To use this local database, disable Vector Store in the Integrations settings.', 'mxchat'); ?></span>
        </div>
        <?php endif; ?>

        <div class="mxch-card">
            <div class="mxch-card-header">
                <h3 class="mxch-card-title">
                    <?php esc_html_e('Knowledge Entries', 'mxchat'); ?>
                    <span id="mxchat-entry-count" style="font-weight: normal; color: var(--mxch-text-secondary);">(<?php echo esc_html($total_records); ?>)</span>
                    <?php if (!empty($use_vectorstore)) : ?>
                        <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; margin-left: 8px; background: #fff3e0; color: #e65100;">
                            <span class="dashicons dashicons-database" style="font-size: 14px;"></span>
                            <?php esc_html_e('Not in use', 'mxchat'); ?>
                        </span>
                    <?php elseif ($use_pinecone) : ?>
                        <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; margin-left: 8px; background: #e3f2fd; color: #1976d2;">
                            <span class="dashicons dashicons-cloud" style="font-size: 14px;"></span>
                            <?php esc_html_e('Pinecone', 'mxchat'); ?>
                        </span>
                    <?php else : ?>
                        <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; margin-left: 8px; background: #f3e5f5; color: #7b1fa2;">
                            <span class="dashicons dashicons-database-view" style="font-size: 14px;"></span>
                            <?php esc_html_e('WordPress DB', 'mxchat'); ?>
                        </span>
                    <?php endif; ?>
                </h3>
                <div class="mxch-kb-header-controls">
                    <form method="get" id="knowledge-search" class="mxch-kb-search-form">
                        <?php wp_nonce_field('mxchat_prompts_search_nonce'); ?>
                        <input type="hidden" name="page" value="mxchat-prompts" />
                        <?php if ($multibot_active && !empty($current_bot_id) && $current_bot_id !== 'default') : ?>
                            <input type="hidden" name="bot_id" value="<?php echo esc_attr($current_bot_id); ?>" />
                        <?php endif; ?>
                        <input type="text" name="search" class="mxch-input mxch-input-sm mxch-kb-search-input" placeholder="<?php esc_attr_e('Search...', 'mxchat'); ?>" value="<?php echo esc_attr($search_query); ?>" />
                        <select name="content_type" class="mxch-select mxch-kb-type-filter" onchange="this.form.submit()">
                            <option value=""><?php esc_html_e('All Types', 'mxchat'); ?></option>
                            <?php
                            $post_types = get_post_types(array('public' => true), 'objects');
                            foreach ($post_types as $post_type) {
                                echo '<option value="' . esc_attr($post_type->name) . '" ' . selected($content_type_filter, $post_type->name, false) . '>' . esc_html($post_type->label) . '</option>';
                            }
                            ?>
                            <option value="pdf" <?php selected($content_type_filter, 'pdf'); ?>><?php esc_html_e('PDFs', 'mxchat'); ?></option>
                            <option value="url" <?php selected($content_type_filter, 'url'); ?>><?php esc_html_e('URLs', 'mxchat'); ?></option>
                        </select>
                    </form>
                    <!-- Delete Button Container - transforms between Delete All and Delete Selected -->
                    <div class="mxchat-delete-container" id="mxchat-delete-container">
                        <!-- Delete All Form (shown when nothing selected) -->
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php?action=mxchat_delete_all_prompts')); ?>" id="mxchat-delete-all-form" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete all knowledge?', 'mxchat'); ?>');">
                            <?php wp_nonce_field('mxchat_delete_all_prompts_action', 'mxchat_delete_all_prompts_nonce'); ?>
                            <input type="hidden" name="data_source" value="<?php echo esc_attr($data_source); ?>" />
                            <input type="hidden" name="bot_id" value="<?php echo esc_attr($current_bot_id); ?>" />
                            <button type="submit" class="mxch-btn mxch-btn-secondary mxch-btn-sm" style="color: var(--mxch-error);">
                                <span class="dashicons dashicons-trash" style="font-size: 14px;"></span>
                                <?php esc_html_e('Delete All', 'mxchat'); ?>
                            </button>
                        </form>
                        <!-- Delete Selected Button (shown when items selected) -->
                        <button type="button"
                                class="mxch-btn mxch-btn-secondary mxch-btn-sm mxchat-bulk-delete"
                                id="mxchat-delete-selected-entries"
                                style="display: none; color: var(--mxch-error);"
                                data-nonce="<?php echo wp_create_nonce('mxchat_bulk_delete_knowledge_nonce'); ?>"
                                data-bot-id="<?php echo esc_attr($current_bot_id); ?>"
                                data-data-source="<?php echo esc_attr($data_source); ?>">
                            <span class="dashicons dashicons-trash" style="font-size: 14px;"></span>
                            <span class="mxchat-bulk-delete-text"><?php esc_html_e('Delete Selected', 'mxchat'); ?></span>
                            <span class="mxchat-selected-count" id="mxchat-selected-entry-count"></span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="mxch-card-body" style="padding: 0;">
                <?php if ($use_pinecone) : ?>
                    <div class="mxch-notice mxch-notice-info mxch-pinecone-notice" style="margin: 16px; border-radius: var(--mxch-radius-md); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <span><?php esc_html_e('Changes may take up to 60 seconds to process in Pinecone. Click "Refresh Entries" to see updates.', 'mxchat'); ?></span>
                        </div>
                        <button type="button" id="mxchat-refresh-pinecone-entries" class="mxch-btn mxch-btn-secondary mxch-btn-sm">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Refresh Entries', 'mxchat'); ?>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="mxchat-table-wrapper" style="overflow-x: auto;">
                    <table class="mxchat-records-table" id="mxchat-records-table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 1px solid var(--mxch-card-border);">
                                <th style="padding: 12px 16px; width: 40px; text-align: center;">
                                    <input type="checkbox" id="mxchat-select-all-entries" class="mxchat-entry-checkbox-all" title="<?php esc_attr_e('Select All', 'mxchat'); ?>">
                                </th>
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: var(--mxch-text-secondary); font-size: 12px; text-transform: uppercase;"><?php esc_html_e('ID', 'mxchat'); ?></th>
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: var(--mxch-text-secondary); font-size: 12px; text-transform: uppercase;"><?php esc_html_e('Content', 'mxchat'); ?></th>
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: var(--mxch-text-secondary); font-size: 12px; text-transform: uppercase;"><?php esc_html_e('Source', 'mxchat'); ?></th>
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: var(--mxch-text-secondary); font-size: 12px; text-transform: uppercase;"><?php esc_html_e('Actions', 'mxchat'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="mxchat-entries-tbody">
                            <?php if (empty($grouped_prompts)) : ?>
                                <tr>
                                    <td colspan="5" style="padding: 40px; text-align: center; color: var(--mxch-text-muted);">
                                        <?php esc_html_e('No knowledge entries found. Use the Import Options to add content.', 'mxchat'); ?>
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php
                                // Display grouped prompts
                                foreach ($grouped_prompts as $source_url => $group) :
                                    $chunk_count = count($group);
                                    $first_prompt = $group[0];
                                    $display_index++;

                                    if ($chunk_count > 1) :
                                        // Multiple chunks - show grouped row with expand button
                                        $group_id = 'group-' . md5($source_url);
                                ?>
                                        <tr id="prompt-<?php echo esc_attr($first_prompt->id); ?>"
                                            class="mxchat-chunk-group-header"
                                            data-source="<?php echo esc_attr($data_source); ?>"
                                            data-group-id="<?php echo esc_attr($group_id); ?>"
                                            style="border-bottom: 1px solid var(--mxch-card-border); <?php if ($data_source === 'pinecone') echo 'background: rgba(33, 150, 243, 0.02);'; ?>">
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
                                                <?php if ($data_source === 'pinecone') : ?>
                                                    <?php echo esc_html($display_index + (($current_page - 1) * $per_page)); ?>
                                                <?php else : ?>
                                                    <?php echo esc_html($first_prompt->id); ?>
                                                <?php endif; ?>
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
                                        foreach ($group as $chunk_index => $prompt) :
                                            $meta_chunk_index = isset($prompt->chunk_metadata['chunk_index']) ? intval($prompt->chunk_metadata['chunk_index']) : $chunk_index;
                                            $meta_total_chunks = isset($prompt->chunk_metadata['total_chunks']) ? intval($prompt->chunk_metadata['total_chunks']) : $chunk_count;
                                            $content = isset($prompt->display_content) ? $prompt->display_content : $prompt->article_content;
                                            $preview_length = 150;
                                            $content_preview = mb_strlen($content) > $preview_length
                                                ? mb_substr($content, 0, $preview_length) . '...'
                                                : $content;
                                        ?>
                                        <tr id="prompt-<?php echo esc_attr($prompt->id); ?>"
                                            class="mxchat-chunk-row <?php echo esc_attr($group_id); ?>"
                                            data-source="<?php echo esc_attr($data_source); ?>"
                                            style="display: none; background: #f8f9fa; border-bottom: 1px solid var(--mxch-card-border);">
                                            <td style="padding: 12px 16px; text-align: center;">
                                                <!-- Checkbox placeholder for chunk rows -->
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
                                        <?php endforeach; ?>
                                    <?php else :
                                        // Single entry - display normally with accordion
                                        $prompt = $first_prompt;
                                        $content = isset($prompt->display_content) ? $prompt->display_content : $prompt->article_content;
                                        $preview_length = 150;
                                        $content_preview = mb_strlen($content) > $preview_length
                                            ? mb_substr($content, 0, $preview_length) . '...'
                                            : $content;
                                    ?>
                                    <tr id="prompt-<?php echo esc_attr($prompt->id); ?>"
                                        data-source="<?php echo esc_attr($data_source); ?>"
                                        style="border-bottom: 1px solid var(--mxch-card-border); <?php if ($data_source === 'pinecone') echo 'background: rgba(33, 150, 243, 0.02);'; ?>">
                                        <td style="padding: 12px 16px; text-align: center;">
                                            <input type="checkbox"
                                                   class="mxchat-entry-checkbox"
                                                   data-entry-id="<?php echo esc_attr($prompt->id); ?>"
                                                   data-source="<?php echo esc_attr($data_source); ?>"
                                                   data-source-url="<?php echo esc_attr($source_url); ?>"
                                                   data-is-group="false">
                                        </td>
                                        <td style="padding: 12px 16px; font-size: 13px;">
                                            <?php if ($data_source === 'pinecone') : ?>
                                                <?php echo esc_html($display_index + (($current_page - 1) * $per_page)); ?>
                                            <?php else : ?>
                                                <?php echo esc_html($prompt->id); ?>
                                            <?php endif; ?>
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
                                            <?php if ($data_source === 'pinecone') : ?>
                                                <button type="button" class="mxch-btn mxch-btn-ghost mxch-btn-sm delete-button-ajax" data-vector-id="<?php echo esc_attr($prompt->id); ?>" data-bot-id="<?php echo esc_attr($current_bot_id); ?>" data-nonce="<?php echo wp_create_nonce('mxchat_delete_pinecone_prompt_nonce'); ?>" style="color: var(--mxch-error);">
                                                    <span class="dashicons dashicons-trash" style="font-size: 14px;"></span>
                                                </button>
                                            <?php else : ?>
                                                <button type="button" class="mxch-btn mxch-btn-ghost mxch-btn-sm delete-button-wordpress" data-entry-id="<?php echo esc_attr($prompt->id); ?>" data-bot-id="<?php echo esc_attr($current_bot_id); ?>" data-nonce="<?php echo wp_create_nonce('mxchat_delete_wordpress_prompt_nonce'); ?>" style="color: var(--mxch-error);">
                                                    <span class="dashicons dashicons-trash" style="font-size: 14px;"></span>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination wrapper - always present so JS can populate it after processing -->
                <div id="mxchat-kb-pagination" class="mxchat-kb-pagination-wrapper" style="<?php echo $total_pages > 1 ? 'padding: 16px; border-top: 1px solid var(--mxch-card-border); text-align: center;' : ''; ?>" data-current-page="<?php echo esc_attr($current_page); ?>" data-total-pages="<?php echo esc_attr($total_pages); ?>" data-search="<?php echo esc_attr($search_query); ?>" data-content-type="<?php echo esc_attr($content_type_filter); ?>">
                    <?php if ($total_pages > 1) : ?>
                    <div class="mxchat-ajax-pagination" data-current-page="<?php echo esc_attr($current_page); ?>" data-total-pages="<?php echo esc_attr($total_pages); ?>">
                        <?php if ($current_page > 1) : ?>
                            <a href="#" class="mxchat-page-link" data-page="<?php echo ($current_page - 1); ?>"><?php esc_html_e('&laquo; Previous', 'mxchat'); ?></a>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);

                        if ($start_page > 1) {
                            echo '<a href="#" class="mxchat-page-link" data-page="1">1</a> ';
                            if ($start_page > 2) {
                                echo '<span class="mxchat-page-dots">...</span> ';
                            }
                        }

                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i == $current_page) {
                                echo '<span class="mxchat-page-current">' . $i . '</span> ';
                            } else {
                                echo '<a href="#" class="mxchat-page-link" data-page="' . $i . '">' . $i . '</a> ';
                            }
                        }

                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="mxchat-page-dots">...</span> ';
                            }
                            echo '<a href="#" class="mxchat-page-link" data-page="' . $total_pages . '">' . $total_pages . '</a> ';
                        }
                        ?>

                        <?php if ($current_page < $total_pages) : ?>
                            <a href="#" class="mxchat-page-link" data-page="<?php echo ($current_page + 1); ?>"><?php esc_html_e('Next &raquo;', 'mxchat'); ?></a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render Auto-Sync Section
 */
function mxchat_render_auto_sync_section($knowledge_manager) {
    ?>
    <div id="auto-sync" class="mxch-section">
        <div class="mxch-content-header">
            <h1 class="mxch-content-title"><?php esc_html_e('Auto-Sync Settings', 'mxchat'); ?></h1>
            <p class="mxch-content-subtitle"><?php esc_html_e('Automatically sync WordPress content to your knowledge base when published or updated.', 'mxchat'); ?></p>
        </div>

        <div class="mxch-card">
            <div class="mxch-card-body mxchat-autosave-section">
                <div class="mxch-field">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                        <label class="mxchat-toggle-switch">
                            <input type="checkbox" name="mxchat_auto_sync_posts" class="mxchat-autosave-field" value="1" data-nonce="<?php echo wp_create_nonce('mxchat_prompts_setting_nonce'); ?>" <?php checked(get_option('mxchat_auto_sync_posts', '0'), '1'); ?>>
                            <span class="mxchat-toggle-slider"></span>
                        </label>
                        <span style="font-weight: 500;"><?php esc_html_e('Auto-sync Posts', 'mxchat'); ?></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <label class="mxchat-toggle-switch">
                            <input type="checkbox" name="mxchat_auto_sync_pages" class="mxchat-autosave-field" value="1" data-nonce="<?php echo wp_create_nonce('mxchat_prompts_setting_nonce'); ?>" <?php checked(get_option('mxchat_auto_sync_pages', '0'), '1'); ?>>
                            <span class="mxchat-toggle-slider"></span>
                        </label>
                        <span style="font-weight: 500;"><?php esc_html_e('Auto-sync Pages', 'mxchat'); ?></span>
                    </div>
                </div>

                <div style="margin-top: 24px;">
                    <button id="mxchat-custom-post-types-toggle" class="mxch-btn mxch-btn-secondary">
                        <?php esc_html_e('Advanced Custom Post Sync Settings', 'mxchat'); ?>
                        <span style="margin-left: 5px;">▼</span>
                    </button>
                    <div id="mxchat-custom-post-types-container" style="display: none; margin-top: 16px; padding: 16px; background: #f8fafc; border-radius: var(--mxch-radius-md);">
                        <h4 style="margin: 0 0 12px 0;"><?php esc_html_e('Sync Custom Post Types', 'mxchat'); ?></h4>
                        <?php
                        $post_types = $knowledge_manager->mxchat_get_public_post_types();
                        unset($post_types['post'], $post_types['page']);

                        if (!empty($post_types)) {
                            foreach ($post_types as $post_type => $label) {
                                $option_name = 'mxchat_auto_sync_' . $post_type;
                                $is_enabled = get_option($option_name, '0');
                                ?>
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                    <label class="mxchat-toggle-switch">
                                        <input type="checkbox" name="<?php echo esc_attr($option_name); ?>" class="mxchat-autosave-field" value="1" data-nonce="<?php echo wp_create_nonce('mxchat_prompts_setting_nonce'); ?>" <?php checked($is_enabled, '1'); ?>>
                                        <span class="mxchat-toggle-slider"></span>
                                    </label>
                                    <span><?php echo esc_html($label); ?> (<?php echo esc_html($post_type); ?>)</span>
                                </div>
                                <?php
                            }
                        } else {
                            echo '<p style="color: var(--mxch-text-muted);">' . esc_html__('No custom post types found.', 'mxchat') . '</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render Chunking Section
 */
function mxchat_render_chunking_section() {
    $chunking_settings = MxChat_Chunker::get_settings();
    $chunking_enabled = $chunking_settings['chunking_enabled'];
    $chunk_size = $chunking_settings['chunk_size'];
    ?>
    <div id="chunking" class="mxch-section">
        <div class="mxch-content-header">
            <h1 class="mxch-content-title"><?php esc_html_e('Content Chunking', 'mxchat'); ?></h1>
            <p class="mxch-content-subtitle"><?php esc_html_e('Split large content into smaller segments for more accurate semantic search.', 'mxchat'); ?></p>
        </div>

        <div class="mxch-card">
            <div class="mxch-card-body mxchat-autosave-section">
                <div class="mxch-notice mxch-notice-info" style="margin-bottom: 20px;">
                    <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <span><?php esc_html_e('Chunking improves retrieval quality for long documents. All chunks are reassembled before sending to the AI. Only applies to new submissions.', 'mxchat'); ?></span>
                </div>

                <div class="mxch-field">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                        <label class="mxchat-toggle-switch">
                            <input type="checkbox" name="mxchat_chunking_enabled" id="mxchat_chunking_enabled" class="mxchat-autosave-field" value="1" data-option-name="mxchat_chunking_enabled" data-nonce="<?php echo wp_create_nonce('mxchat_prompts_setting_nonce'); ?>" <?php checked($chunking_enabled, true); ?>>
                            <span class="mxchat-toggle-slider"></span>
                        </label>
                        <span style="font-weight: 500;"><?php esc_html_e('Enable Content Chunking', 'mxchat'); ?></span>
                    </div>
                </div>

                <div class="mxch-field">
                    <label class="mxch-field-label" for="mxchat_chunk_size"><?php esc_html_e('Chunk Size (characters)', 'mxchat'); ?></label>
                    <input type="number" name="mxchat_chunk_size" id="mxchat_chunk_size" class="mxch-input mxchat-autosave-field" value="<?php echo esc_attr($chunk_size); ?>" min="1000" max="10000" step="500" data-option-name="mxchat_chunk_size" data-nonce="<?php echo wp_create_nonce('mxchat_prompts_setting_nonce'); ?>" style="max-width: 200px;">
                    <p class="mxch-field-description"><?php esc_html_e('Recommended: 4000 characters (~1000 tokens). Range: 1000-10000. Larger chunks preserve more context.', 'mxchat'); ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render Role Restrictions Section
 */
function mxchat_render_role_restrictions_section($knowledge_manager) {
    $role_options = array(
        'public' => __('Public (Everyone)', 'mxchat'),
        'logged_in' => __('Logged In Users', 'mxchat'),
        'subscriber' => __('Subscribers & Above', 'mxchat'),
        'contributor' => __('Contributors & Above', 'mxchat'),
        'author' => __('Authors & Above', 'mxchat'),
        'editor' => __('Editors & Above', 'mxchat'),
        'administrator' => __('Administrators Only', 'mxchat')
    );
    ?>
    <div id="role-restrictions" class="mxch-section">
        <div class="mxch-content-header">
            <h1 class="mxch-content-title"><?php esc_html_e('Role-Based Content Restrictions', 'mxchat'); ?></h1>
            <p class="mxch-content-subtitle"><?php esc_html_e('Automatically restrict content access based on WordPress tags.', 'mxchat'); ?></p>
        </div>

        <div class="mxch-card">
            <div class="mxch-card-body">
                <div class="mxch-notice mxch-notice-info" style="margin-bottom: 20px;">
                    <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <span><strong><?php esc_html_e('How it works:', 'mxchat'); ?></strong> <?php esc_html_e('Add a tag below and select which role should have access. Content with that tag will be restricted to that role level.', 'mxchat'); ?></span>
                </div>

                <h4 style="margin: 0 0 16px 0;"><?php esc_html_e('Add Tag-Role Mapping', 'mxchat'); ?></h4>
                <div style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 24px;">
                    <div class="mxch-field" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                        <label class="mxch-field-label" for="mxchat-tag-input"><?php esc_html_e('Tag Name', 'mxchat'); ?></label>
                        <input type="text" id="mxchat-tag-input" class="mxch-input" placeholder="<?php esc_attr_e('e.g., premium, members-only', 'mxchat'); ?>">
                    </div>
                    <div class="mxch-field" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                        <label class="mxch-field-label" for="mxchat-role-select"><?php esc_html_e('Required Role', 'mxchat'); ?></label>
                        <select id="mxchat-role-select" class="mxch-select">
                            <?php foreach ($role_options as $role_key => $role_label) : ?>
                                <option value="<?php echo esc_attr($role_key); ?>"><?php echo esc_html($role_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" id="mxchat-add-tag-role" class="mxch-btn mxch-btn-primary">
                        <span class="dashicons dashicons-plus-alt" style="font-size: 16px;"></span>
                        <?php esc_html_e('Add Mapping', 'mxchat'); ?>
                    </button>
                </div>

                <h4 style="margin: 24px 0 16px 0;"><?php esc_html_e('Current Tag-Role Mappings', 'mxchat'); ?></h4>
                <div id="mxchat-mappings-container">
                    <div class="mxchat-loading-mappings" style="text-align: center; padding: 20px; color: var(--mxch-text-muted);">
                        <?php esc_html_e('Loading mappings...', 'mxchat'); ?>
                    </div>
                </div>
                <div id="mxchat-no-mappings" style="display: none; text-align: center; padding: 40px; color: var(--mxch-text-muted);">
                    <span class="dashicons dashicons-tag" style="font-size: 48px; opacity: 0.3;"></span>
                    <p><?php esc_html_e('No tag-role mappings yet. Add your first mapping above.', 'mxchat'); ?></p>
                </div>

                <div style="margin-top: 30px; padding-top: 24px; border-top: 1px solid var(--mxch-card-border);">
                    <h4 style="margin: 0 0 12px 0;"><?php esc_html_e('Bulk Update Existing Content', 'mxchat'); ?></h4>
                    <p class="mxch-field-description" style="margin-bottom: 16px;"><?php esc_html_e('Apply role restrictions to all existing content that has the mapped tags.', 'mxchat'); ?></p>
                    <button type="button" id="mxchat-bulk-update-roles" class="mxch-btn mxch-btn-secondary">
                        <span class="dashicons dashicons-update" style="font-size: 16px;"></span>
                        <?php esc_html_e('Update All Existing Content', 'mxchat'); ?>
                    </button>
                    <div id="mxchat-bulk-update-progress" style="display: none; margin-top: 15px;">
                        <div style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                            <div class="mxchat-progress-fill" style="height: 100%; background: var(--mxch-primary); width: 0%; transition: width 0.3s;"></div>
                        </div>
                        <p class="mxchat-progress-text" style="margin-top: 10px; color: var(--mxch-text-secondary);"><?php esc_html_e('Processing...', 'mxchat'); ?></p>
                    </div>
                    <div id="mxchat-bulk-update-result" style="display: none; margin-top: 15px;"></div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render ACF Fields Section
 */
function mxchat_render_acf_fields_section($knowledge_manager) {
    $excluded_fields = get_option('mxchat_acf_excluded_fields', array());
    if (!is_array($excluded_fields)) {
        $excluded_fields = array();
    }
    ?>
    <div id="acf-fields" class="mxch-section">
        <div class="mxch-content-header">
            <h1 class="mxch-content-title"><?php esc_html_e('ACF Field Settings', 'mxchat'); ?></h1>
            <p class="mxch-content-subtitle"><?php esc_html_e('Control which Advanced Custom Fields are included in knowledge base embeddings.', 'mxchat'); ?></p>
        </div>

        <div class="mxch-card">
            <div class="mxch-card-body mxchat-autosave-section">
                <?php if (!function_exists('acf_get_field_groups')): ?>
                    <div class="mxch-notice mxch-notice-info">
                        <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <span><?php esc_html_e('Advanced Custom Fields (ACF) plugin is not detected. Install and activate ACF to use this feature.', 'mxchat'); ?></span>
                    </div>
                <?php else:
                    $all_acf_fields = $knowledge_manager->mxchat_get_all_acf_fields();

                    if (empty($all_acf_fields)): ?>
                        <div class="mxch-notice mxch-notice-info">
                            <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <span><?php esc_html_e('No ACF field groups found. Create field groups in ACF to control which fields are included in embeddings.', 'mxchat'); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="mxch-notice mxch-notice-info" style="margin-bottom: 20px;">
                            <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <span><?php esc_html_e('Toggle fields ON to include them in knowledge base embeddings. Toggle OFF to exclude sensitive or irrelevant fields.', 'mxchat'); ?></span>
                        </div>

                        <?php foreach ($all_acf_fields as $group_title => $fields): ?>
                            <div class="mxchat-acf-field-group" style="margin-bottom: 24px;">
                                <h4 style="margin: 0 0 12px 0; color: var(--mxch-text-primary); font-weight: 600;">
                                    <?php echo esc_html($group_title); ?>
                                </h4>
                                <div style="background: #f8fafc; border-radius: var(--mxch-radius-md); padding: 16px;">
                                    <?php foreach ($fields as $field):
                                        $field_name = $field['name'];
                                        $is_enabled = !in_array($field_name, $excluded_fields);
                                    ?>
                                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0;">
                                            <div>
                                                <span style="font-weight: 500;"><?php echo esc_html($field['label']); ?></span>
                                                <span style="color: var(--mxch-text-muted); font-size: 12px; margin-left: 8px;">(<?php echo esc_html($field_name); ?>)</span>
                                                <span style="color: var(--mxch-text-muted); font-size: 11px; margin-left: 8px; background: #e2e8f0; padding: 2px 6px; border-radius: 4px;"><?php echo esc_html($field['type']); ?></span>
                                            </div>
                                            <label class="mxchat-toggle-switch">
                                                <input type="checkbox"
                                                       name="mxchat_acf_field_<?php echo esc_attr($field_name); ?>"
                                                       class="mxchat-autosave-field"
                                                       value="on"
                                                       data-nonce="<?php echo wp_create_nonce('mxchat_prompts_setting_nonce'); ?>"
                                                       <?php checked($is_enabled, true); ?>>
                                                <span class="mxchat-toggle-slider"></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render Custom Meta Section
 */
function mxchat_render_custom_meta_section() {
    $whitelist = get_option('mxchat_custom_meta_whitelist', '');
    ?>
    <div id="custom-meta" class="mxch-section">
        <div class="mxch-content-header">
            <h1 class="mxch-content-title"><?php esc_html_e('Custom Post Meta', 'mxchat'); ?></h1>
            <p class="mxch-content-subtitle"><?php esc_html_e('Include custom post meta fields (non-ACF) in knowledge base embeddings.', 'mxchat'); ?></p>
        </div>

        <div class="mxch-card">
            <div class="mxch-card-body mxchat-autosave-section">
                <div class="mxch-notice mxch-notice-info" style="margin-bottom: 20px;">
                    <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <span><?php esc_html_e('Enter post meta keys to include in embeddings. This is useful for OptionTree fields, theme meta boxes, or any custom post meta that is not managed by ACF.', 'mxchat'); ?></span>
                </div>

                <div class="mxch-field">
                    <label class="mxch-field-label" for="mxchat_custom_meta_whitelist">
                        <?php esc_html_e('Meta Key Whitelist', 'mxchat'); ?>
                    </label>
                    <textarea
                        name="mxchat_custom_meta_whitelist"
                        id="mxchat_custom_meta_whitelist"
                        class="mxch-textarea mxchat-autosave-field"
                        rows="6"
                        placeholder="speaker_profession&#10;speaker_company&#10;event_location&#10;_custom_field_key"
                        data-nonce="<?php echo wp_create_nonce('mxchat_prompts_setting_nonce'); ?>"
                        style="font-family: monospace;"
                    ><?php echo esc_textarea($whitelist); ?></textarea>
                    <p class="mxch-field-description">
                        <?php esc_html_e('Enter one meta key per line. These fields will be appended to the content during embedding. Only string values are included.', 'mxchat'); ?>
                    </p>
                </div>

                <div style="margin-top: 20px; padding: 16px; background: #f8fafc; border-radius: var(--mxch-radius-md);">
                    <h4 style="margin: 0 0 12px 0; font-size: 14px;"><?php esc_html_e('How to find meta keys:', 'mxchat'); ?></h4>
                    <ul style="margin: 0; padding-left: 20px; color: var(--mxch-text-secondary); font-size: 13px;">
                        <li><?php esc_html_e('Check your theme documentation for meta key names', 'mxchat'); ?></li>
                        <li><?php esc_html_e('Look in the wp_postmeta database table', 'mxchat'); ?></li>
                        <li><?php esc_html_e('Use a plugin like "Show Post Meta" to view meta keys on any post', 'mxchat'); ?></li>
                        <li><?php esc_html_e('Meta keys starting with underscore (_) are usually hidden/internal', 'mxchat'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render Pinecone Section
 */
function mxchat_render_pinecone_section() {
    $pinecone_options = get_option('mxchat_pinecone_addon_options', array());
    $use_pinecone = $pinecone_options['mxchat_use_pinecone'] ?? '0';
    ?>
    <div id="pinecone" class="mxch-section">
        <div class="mxch-content-header">
            <h1 class="mxch-content-title"><?php esc_html_e('Pinecone Vector Database', 'mxchat'); ?></h1>
            <p class="mxch-content-subtitle"><?php esc_html_e('Configure Pinecone for enhanced search performance with larger knowledge bases.', 'mxchat'); ?></p>
        </div>

        <div class="mxch-card">
            <div class="mxch-card-body">
                <div class="mxch-notice mxch-notice-info" style="margin-bottom: 20px;">
                    <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <span><strong><?php esc_html_e('Pinecone is optional.', 'mxchat'); ?></strong> <?php esc_html_e('MxChat works without it. When enabled, content is stored in Pinecone for faster similarity searches.', 'mxchat'); ?></span>
                </div>

                <form method="post" action="options.php">
                    <?php settings_fields('mxchat_pinecone_addon_options'); ?>

                    <div class="mxch-field">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                            <label class="mxchat-toggle-switch">
                                <input type="checkbox" name="mxchat_pinecone_addon_options[mxchat_use_pinecone]" value="1" <?php checked($use_pinecone, '1'); ?>>
                                <span class="mxchat-toggle-slider"></span>
                            </label>
                            <span style="font-weight: 500;"><?php esc_html_e('Enable Pinecone Database', 'mxchat'); ?></span>
                        </div>
                    </div>

                    <div class="mxchat-pinecone-settings" <?php echo $use_pinecone ? '' : 'style="display: none;"'; ?>>
                        <?php if ($use_pinecone) : ?>
                            <div class="mxch-notice mxch-notice-success" style="margin-bottom: 20px;">
                                <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                <span><?php esc_html_e('Pinecone is enabled. All new knowledge base content will be stored in Pinecone.', 'mxchat'); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="mxch-field">
                            <label class="mxch-field-label" for="mxchat_pinecone_api_key">
                                <?php esc_html_e('Pinecone API Key', 'mxchat'); ?> <span class="mxch-field-label-required">*</span>
                            </label>
                            <input type="password" id="mxchat_pinecone_api_key" name="mxchat_pinecone_addon_options[mxchat_pinecone_api_key]" value="<?php echo esc_attr($pinecone_options['mxchat_pinecone_api_key'] ?? ''); ?>" class="mxch-input" placeholder="pcsk_...">
                            <p class="mxch-field-description">
                                <?php esc_html_e('Found in your Pinecone dashboard under API Keys.', 'mxchat'); ?>
                                <a href="https://app.pinecone.io/" target="_blank"><?php esc_html_e('Open Pinecone Dashboard', 'mxchat'); ?></a>
                            </p>
                        </div>

                        <div class="mxch-field">
                            <label class="mxch-field-label" for="mxchat_pinecone_environment"><?php esc_html_e('Region', 'mxchat'); ?></label>
                            <input type="text" id="mxchat_pinecone_environment" name="mxchat_pinecone_addon_options[mxchat_pinecone_environment]" value="<?php echo esc_attr($pinecone_options['mxchat_pinecone_environment'] ?? ''); ?>" class="mxch-input" placeholder="e.g., gcp-starter">
                            <p class="mxch-field-description"><?php esc_html_e('Your Pinecone environment/region (e.g., gcp-starter, us-west1-gcp)', 'mxchat'); ?></p>
                        </div>

                        <div class="mxch-field">
                            <label class="mxch-field-label" for="mxchat_pinecone_index">
                                <?php esc_html_e('Index Name', 'mxchat'); ?> <span class="mxch-field-label-required">*</span>
                            </label>
                            <input type="text" id="mxchat_pinecone_index" name="mxchat_pinecone_addon_options[mxchat_pinecone_index]" value="<?php echo esc_attr($pinecone_options['mxchat_pinecone_index'] ?? ''); ?>" class="mxch-input" placeholder="e.g., my-wordpress-vectors">
                            <p class="mxch-field-description"><?php esc_html_e('The name of your Pinecone index. Must be created in your Pinecone dashboard first.', 'mxchat'); ?></p>
                        </div>

                        <div class="mxch-field">
                            <label class="mxch-field-label" for="mxchat_pinecone_host">
                                <?php esc_html_e('Pinecone Host', 'mxchat'); ?> <span class="mxch-field-label-required">*</span>
                            </label>
                            <input type="text" id="mxchat_pinecone_host" name="mxchat_pinecone_addon_options[mxchat_pinecone_host]" value="<?php echo esc_attr($pinecone_options['mxchat_pinecone_host'] ?? ''); ?>" class="mxch-input" placeholder="e.g., my-index-xyz123.svc.pinecone.io">
                            <p class="mxch-field-description"><?php esc_html_e('The hostname from your Pinecone index URL (exclude https://). Found in index details.', 'mxchat'); ?></p>
                        </div>
                    </div>

                    <?php submit_button(__('Save Pinecone Settings', 'mxchat'), 'primary', 'submit', true, array('class' => 'mxch-btn mxch-btn-primary')); ?>
                </form>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render OpenAI Vector Store Section
 */
function mxchat_render_openai_vectorstore_section() {
    $vectorstore_options = get_option('mxchat_openai_vectorstore_options', array());
    $use_vectorstore = $vectorstore_options['mxchat_use_openai_vectorstore'] ?? '0';
    $vectorstore_ids = $vectorstore_options['mxchat_vectorstore_ids'] ?? '';
    $max_results = $vectorstore_options['mxchat_vectorstore_max_results'] ?? 5;

    // Get current chat model to check compatibility
    $mxchat_options = get_option('mxchat_options', array());
    $current_model = $mxchat_options['model'] ?? 'gpt-5.1-chat-latest';
    $is_openai_model = preg_match('/^(gpt-|o1-|o3-)/', $current_model);
    ?>
    <div id="openai-vectorstore" class="mxch-section">
        <div class="mxch-content-header">
            <h1 class="mxch-content-title"><?php esc_html_e('OpenAI Vector Store', 'mxchat'); ?></h1>
            <p class="mxch-content-subtitle"><?php esc_html_e('Use OpenAI\'s hosted vector database for knowledge retrieval. Your documents are stored and searched directly on OpenAI\'s platform.', 'mxchat'); ?></p>
        </div>

        <div class="mxch-card">
            <div class="mxch-card-body">
                <div class="mxch-notice mxch-notice-info" style="margin-bottom: 20px;">
                    <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <span><strong><?php esc_html_e('Requires OpenAI Chat Model.', 'mxchat'); ?></strong> <?php esc_html_e('Vector Store search only works with OpenAI models (gpt-5.1-chat-latest, gpt-5-mini, etc.). Create Vector Stores in your OpenAI Dashboard.', 'mxchat'); ?></span>
                </div>

                <?php if (!$is_openai_model && $use_vectorstore === '1'): ?>
                <div class="mxch-notice mxch-notice-warning" style="margin-bottom: 20px;">
                    <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span><strong><?php esc_html_e('Model Incompatible:', 'mxchat'); ?></strong> <?php printf(esc_html__('Your current chat model (%s) is not an OpenAI model. Vector Store search will not work until you switch to an OpenAI model.', 'mxchat'), esc_html($current_model)); ?></span>
                </div>
                <?php endif; ?>

                <form method="post" action="options.php" id="mxchat-vectorstore-form">
                    <?php settings_fields('mxchat_openai_vectorstore_options'); ?>

                    <div class="mxch-field">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                            <label class="mxchat-toggle-switch">
                                <input type="checkbox" name="mxchat_openai_vectorstore_options[mxchat_use_openai_vectorstore]" value="1" <?php checked($use_vectorstore, '1'); ?> id="mxchat_use_openai_vectorstore">
                                <span class="mxchat-toggle-slider"></span>
                            </label>
                            <span style="font-weight: 500;"><?php esc_html_e('Enable OpenAI Vector Store', 'mxchat'); ?></span>
                        </div>
                    </div>

                    <div class="mxchat-vectorstore-settings" <?php echo $use_vectorstore === '1' ? '' : 'style="display: none;"'; ?>>
                        <?php if ($use_vectorstore === '1'): ?>
                            <div class="mxch-notice mxch-notice-success" style="margin-bottom: 20px;">
                                <svg class="mxch-notice-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                <span><?php esc_html_e('OpenAI Vector Store is enabled. Queries will search your Vector Store for relevant content.', 'mxchat'); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="mxch-field">
                            <label class="mxch-field-label" for="mxchat_vectorstore_ids">
                                <?php esc_html_e('Vector Store ID(s)', 'mxchat'); ?> <span class="mxch-field-label-required">*</span>
                            </label>
                            <input type="text" id="mxchat_vectorstore_ids" name="mxchat_openai_vectorstore_options[mxchat_vectorstore_ids]" value="<?php echo esc_attr($vectorstore_ids); ?>" class="mxch-input" placeholder="vs_abc123xyz">
                            <p class="mxch-field-description">
                                <?php esc_html_e('Enter your Vector Store ID from the OpenAI Dashboard. For multiple stores, separate with commas.', 'mxchat'); ?>
                                <a href="https://platform.openai.com/storage/vector_stores" target="_blank"><?php esc_html_e('Open OpenAI Vector Store Dashboard', 'mxchat'); ?></a>
                            </p>
                        </div>

                        <div class="mxch-field">
                            <label class="mxch-field-label" for="mxchat_vectorstore_max_results">
                                <?php esc_html_e('Max Results', 'mxchat'); ?>
                            </label>
                            <select id="mxchat_vectorstore_max_results" name="mxchat_openai_vectorstore_options[mxchat_vectorstore_max_results]" class="mxch-input" style="width: auto;">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php selected($max_results, $i); ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                            <p class="mxch-field-description"><?php esc_html_e('Maximum number of content chunks to retrieve from the Vector Store.', 'mxchat'); ?></p>
                        </div>
                    </div>

                    <?php submit_button(__('Save Vector Store Settings', 'mxchat'), 'primary', 'submit', true, array('class' => 'mxch-btn mxch-btn-primary')); ?>
                </form>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render Content Selector Modal
 */
function mxchat_render_content_selector_modal() {
    ?>
    <div id="mxchat-kb-content-selector-modal" class="mxchat-kb-modal">
        <div class="mxchat-kb-modal-content">
            <div class="mxchat-kb-modal-header">
                <h3>
                    <?php esc_html_e('Select WordPress Content', 'mxchat'); ?><br>
                    <span class="mxchat-kb-header-note"><?php esc_html_e('(Content imported here will be tagged "In Knowledge Base")', 'mxchat'); ?></span>
                </h3>
                <span class="mxchat-kb-modal-close">&times;</span>
            </div>
            <div class="mxchat-kb-modal-filters">
                <div class="mxchat-kb-search-group">
                    <input type="text" id="mxchat-kb-content-search" placeholder="<?php esc_attr_e('Search...', 'mxchat'); ?>">
                </div>
                <div class="mxchat-kb-filter-group">
                    <select id="mxchat-kb-content-type-filter">
                        <option value="all"><?php esc_html_e('All Content Types', 'mxchat'); ?></option>
                        <option value="post"><?php esc_html_e('Posts', 'mxchat'); ?></option>
                        <option value="page"><?php esc_html_e('Pages', 'mxchat'); ?></option>
                        <?php
                        $post_types = get_post_types(array('public' => true), 'objects');
                        foreach ($post_types as $post_type) {
                            if (!in_array($post_type->name, array('post', 'page'))) {
                                echo '<option value="' . esc_attr($post_type->name) . '">' . esc_html($post_type->label) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <select id="mxchat-kb-content-status-filter">
                        <option value="publish"><?php esc_html_e('Published', 'mxchat'); ?></option>
                        <option value="draft"><?php esc_html_e('Drafts', 'mxchat'); ?></option>
                        <option value="all"><?php esc_html_e('All Statuses', 'mxchat'); ?></option>
                    </select>
                    <select id="mxchat-kb-processed-filter">
                        <option value="all"><?php esc_html_e('All Content', 'mxchat'); ?></option>
                        <option value="processed"><?php esc_html_e('In Knowledge Base', 'mxchat'); ?></option>
                        <option value="unprocessed"><?php esc_html_e('Not In Knowledge Base', 'mxchat'); ?></option>
                    </select>
                </div>
            </div>
            <div class="mxchat-kb-content-selection">
                <div class="mxchat-kb-selection-header">
                    <label>
                        <input type="checkbox" id="mxchat-kb-select-all">
                        <?php esc_html_e('Select All on Page', 'mxchat'); ?>
                    </label>
                    <span class="mxchat-kb-selection-count">0 <?php esc_html_e('selected', 'mxchat'); ?></span>
                    <span class="mxchat-kb-selection-hint" style="margin-left: auto; font-size: 12px; color: var(--mxch-text-muted);">
                        <span class="dashicons dashicons-info-outline" style="font-size: 14px; vertical-align: middle;"></span>
                        <?php esc_html_e('Selections persist across pages', 'mxchat'); ?>
                    </span>
                </div>
                <div class="mxchat-kb-content-list">
                    <div class="mxchat-kb-loading">
                        <span class="mxchat-kb-spinner is-active"></span>
                        <?php esc_html_e('Loading content...', 'mxchat'); ?>
                    </div>
                </div>
            </div>
            <div class="mxchat-kb-modal-footer">
                <div class="mxchat-kb-pagination"></div>
                <div class="mxchat-kb-footer-actions">
                    <button type="button" id="mxchat-kb-process-selected" class="mxchat-kb-button-primary" disabled>
                        <?php esc_html_e('Process Selected Content', 'mxchat'); ?>
                        <span class="mxchat-kb-selected-count">(0)</span>
                    </button>
                    <button type="button" class="mxchat-kb-button-secondary mxchat-kb-modal-close">
                        <?php esc_html_e('Cancel', 'mxchat'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render Knowledge Page Navigation Scripts
 */
function mxchat_render_knowledge_page_scripts() {
    ?>
    <script>
    (function() {
        'use strict';

        // Wait for DOM
        document.addEventListener('DOMContentLoaded', function() {
            initKnowledgeNavigation();
            initMobileMenu();
            initImportOptions();
            initCustomPostTypesToggle();
            // Sitemap detection is now initialized when user clicks the Sitemap Import option
        });

        function initKnowledgeNavigation() {
            const navLinks = document.querySelectorAll('.mxch-nav-link[data-target], .mxch-mobile-nav-link[data-target]');
            const sections = document.querySelectorAll('.mxch-section');

            function showSection(targetId) {
                // Hide all sections
                sections.forEach(section => section.classList.remove('active'));
                // Show target section
                const target = document.getElementById(targetId);
                if (target) {
                    target.classList.add('active');
                }
                // Update nav links
                navLinks.forEach(link => {
                    link.classList.toggle('active', link.dataset.target === targetId);
                });
                // Update URL hash
                history.replaceState(null, null, '#' + targetId);
            }

            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.dataset.target;
                    showSection(targetId);
                    // Close mobile menu if open
                    const mobileMenu = document.querySelector('.mxch-mobile-menu');
                    const overlay = document.querySelector('.mxch-mobile-overlay');
                    if (mobileMenu) mobileMenu.classList.remove('open');
                    if (overlay) overlay.classList.remove('open');
                });
            });

            // Handle initial hash
            const hash = window.location.hash.substring(1);
            if (hash && document.getElementById(hash)) {
                showSection(hash);
            }
        }

        function initMobileMenu() {
            const menuBtn = document.querySelector('.mxch-mobile-menu-btn');
            const closeBtn = document.querySelector('.mxch-mobile-menu-close');
            const overlay = document.querySelector('.mxch-mobile-overlay');
            const menu = document.querySelector('.mxch-mobile-menu');

            if (menuBtn && menu) {
                menuBtn.addEventListener('click', function() {
                    menu.classList.add('open');
                    if (overlay) overlay.classList.add('open');
                });
            }

            if (closeBtn && menu) {
                closeBtn.addEventListener('click', function() {
                    menu.classList.remove('open');
                    if (overlay) overlay.classList.remove('open');
                });
            }

            if (overlay && menu) {
                overlay.addEventListener('click', function() {
                    menu.classList.remove('open');
                    overlay.classList.remove('open');
                });
            }
        }

        function initImportOptions() {
            const importBoxes = document.querySelectorAll('.mxchat-import-box');
            const urlInputArea = document.getElementById('mxchat-url-input-area');
            const contentInputArea = document.getElementById('mxchat-content-input-area');
            const urlInput = document.getElementById('sitemap_url');
            const importTypeField = document.getElementById('import_type');
            const descriptionText = document.getElementById('url-description-text');

            // Sitemap detection elements
            const sitemapsLoading = document.getElementById('mxchat-sitemaps-loading');
            const detectedSitemaps = document.getElementById('mxchat-detected-sitemaps');
            const noSitemaps = document.getElementById('mxchat-no-sitemaps');

            // Helper to hide all sitemap detection UI
            function hideSitemapDetection() {
                if (sitemapsLoading) sitemapsLoading.style.display = 'none';
                if (detectedSitemaps) detectedSitemaps.style.display = 'none';
                if (noSitemaps) noSitemaps.style.display = 'none';
            }

            importBoxes.forEach(box => {
                box.addEventListener('click', function() {
                    const option = this.dataset.option;
                    const type = this.dataset.type;

                    // Remove active from all boxes
                    importBoxes.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    // Handle different import types
                    if (option === 'wordpress') {
                        // WordPress content selector - handled by existing modal
                        hideSitemapDetection();
                        return;
                    }

                    if (option === 'content') {
                        if (urlInputArea) urlInputArea.style.display = 'none';
                        if (contentInputArea) contentInputArea.style.display = 'block';
                        hideSitemapDetection();
                    } else if (type === 'sitemap' || type === 'url' || type === 'pdf') {
                        if (contentInputArea) contentInputArea.style.display = 'none';
                        if (urlInputArea) urlInputArea.style.display = 'block';

                        if (urlInput) {
                            urlInput.placeholder = this.dataset.placeholder || 'Enter URL here';
                        }
                        if (importTypeField) {
                            importTypeField.value = type;
                        }
                        if (descriptionText) {
                            const descriptions = {
                                'sitemap': '<?php echo esc_js(__('Use a content-specific sub-sitemap (e.g., post-sitemap.xml), not the main sitemap index.', 'mxchat')); ?>',
                                'url': '<?php echo esc_js(__('Enter the URL of any webpage to import its content.', 'mxchat')); ?>',
                                'pdf': '<?php echo esc_js(__('Enter the URL of a publicly accessible PDF file.', 'mxchat')); ?>'
                            };
                            descriptionText.textContent = descriptions[type] || '';
                        }

                        // Only show sitemap detection when Sitemap Import is selected
                        if (type === 'sitemap') {
                            // Initialize sitemap detection (function is defined in knowledge-processing.js)
                            if (typeof window.mxchatInitSitemapDetection === 'function') {
                                window.mxchatInitSitemapDetection();
                            }
                        } else {
                            hideSitemapDetection();
                        }
                    }
                });
            });
        }

        function initCustomPostTypesToggle() {
            const toggleBtn = document.getElementById('mxchat-custom-post-types-toggle');
            const container = document.getElementById('mxchat-custom-post-types-container');

            if (toggleBtn && container) {
                toggleBtn.addEventListener('click', function() {
                    const isHidden = container.style.display === 'none';
                    container.style.display = isHidden ? 'block' : 'none';
                    const icon = this.querySelector('span:last-child');
                    if (icon) {
                        icon.textContent = isHidden ? '▲' : '▼';
                    }
                });
            }
        }

        // Sitemap detection is now handled by knowledge-processing.js
        // It will be initialized when user clicks "Sitemap Import" option
    })();
    </script>
    <?php
}
