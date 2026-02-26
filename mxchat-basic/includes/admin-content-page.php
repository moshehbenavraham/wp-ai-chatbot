<?php
/**
 * MxChat Content Generator Page
 *
 * Admin page template with sidebar navigation for content generation.
 *
 * @package MxChat
 * @since 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function mxchat_render_content_page($admin_instance) {
    $is_activated = $admin_instance->is_activated();
    $options = get_option('mxchat_options', array());
    $plugin_url = plugin_dir_url(dirname(__FILE__));

    // Pro feature checks — add-on plugins hook into this filter to unlock
    $pro_internal_linking  = apply_filters('mxchat_content_pro_feature', false, 'internal_linking');
    $pro_tool_use          = apply_filters('mxchat_content_pro_feature', false, 'tool_use');
    $pro_image_management  = apply_filters('mxchat_content_pro_feature', false, 'image_management');
    $pro_meta_management   = apply_filters('mxchat_content_pro_feature', false, 'meta_management');
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
                <span class="mxch-mobile-menu-title"><?php esc_html_e('Content', 'mxchat'); ?></span>
                <button type="button" class="mxch-mobile-menu-close" aria-label="<?php esc_attr_e('Close menu', 'mxchat'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <nav class="mxch-mobile-menu-nav">
                <div class="mxch-mobile-nav-section">
                    <div class="mxch-mobile-nav-section-title"><?php esc_html_e('Content', 'mxchat'); ?></div>
                    <button class="mxch-mobile-nav-link active" data-target="content-generate">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"/></svg>
                        <span><?php esc_html_e('Generate', 'mxchat'); ?></span>
                    </button>
                    <button class="mxch-mobile-nav-link" data-target="content-history">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span><?php esc_html_e('History', 'mxchat'); ?></span>
                    </button>
                    <button class="mxch-mobile-nav-link" data-target="content-settings">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                        <span><?php esc_html_e('Settings', 'mxchat'); ?></span>
                    </button>
                </div>
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
                    <span class="mxch-sidebar-version">v<?php echo esc_html(MXCHAT_VERSION ?? '3.1.0'); ?></span>
                </a>
            </div>

            <nav class="mxch-sidebar-nav">
                <div class="mxch-nav-section">
                    <div class="mxch-nav-section-title"><?php esc_html_e('Content', 'mxchat'); ?></div>

                    <div class="mxch-nav-item" data-section="content-generate">
                        <button class="mxch-nav-link active" data-target="content-generate">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('Generate', 'mxchat'); ?></span>
                        </button>
                    </div>

                    <div class="mxch-nav-item" data-section="content-history">
                        <button class="mxch-nav-link" data-target="content-history">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('History', 'mxchat'); ?></span>
                        </button>
                    </div>

                    <div class="mxch-nav-item" data-section="content-settings">
                        <button class="mxch-nav-link" data-target="content-settings">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('Settings', 'mxchat'); ?></span>
                        </button>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="mxch-content">

            <!-- ========================================
                 GENERATE
                 ======================================== -->
            <div id="content-generate" class="mxch-section active">

                <!-- Toolbar -->
                <div class="mxch-cg-toolbar mxch-cg-toolbar-minimal">
                    <div class="mxch-cg-toolbar-left">
                        <button type="button" id="mxch-cg-new-btn" class="mxch-cg-generate-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            <span><?php esc_html_e('Generate', 'mxchat'); ?></span>
                        </button>
                        <h2 id="mxch-cg-preview-title" class="mxch-cg-toolbar-title"><?php esc_html_e('Content Generator', 'mxchat'); ?></h2>
                        <div id="mxch-cg-status-dropdown" class="mxch-cg-status-dropdown" style="display:none;">
                            <button type="button" id="mxch-cg-status-badge" class="mxch-cg-status-badge" title="<?php esc_attr_e('Change status', 'mxchat'); ?>">
                                <span class="mxch-cg-status-badge-text"></span>
                                <svg class="mxch-cg-status-chevron" xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div class="mxch-cg-status-menu" style="display:none;">
                                <button type="button" class="mxch-cg-status-option" data-status="draft">
                                    <span class="mxch-cg-status-dot mxch-cg-dot-draft"></span>
                                    <?php esc_html_e('Draft', 'mxchat'); ?>
                                </button>
                                <button type="button" class="mxch-cg-status-option" data-status="publish">
                                    <span class="mxch-cg-status-dot mxch-cg-dot-publish"></span>
                                    <?php esc_html_e('Published', 'mxchat'); ?>
                                </button>
                                <button type="button" class="mxch-cg-status-option" data-status="future">
                                    <span class="mxch-cg-status-dot mxch-cg-dot-future"></span>
                                    <?php esc_html_e('Scheduled', 'mxchat'); ?>
                                </button>
                                <div class="mxch-cg-status-schedule-row" style="display:none;">
                                    <input type="datetime-local" id="mxch-cg-status-schedule-input" class="mxch-cg-input mxch-cg-status-schedule-input">
                                    <button type="button" id="mxch-cg-status-schedule-confirm" class="mxch-cg-status-schedule-confirm">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mxch-cg-toolbar-right" style="display:none;">
                        <div class="mxch-cg-viewport-toggle">
                            <button type="button" class="mxch-cg-viewport-btn active" data-viewport="desktop" title="<?php esc_attr_e('Desktop', 'mxchat'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            </button>
                            <button type="button" class="mxch-cg-viewport-btn" data-viewport="mobile" title="<?php esc_attr_e('Mobile', 'mxchat'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                            </button>
                        </div>
                        <a id="mxch-cg-view-post" href="#" target="_blank" class="mxch-cg-action-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            <?php esc_html_e('View', 'mxchat'); ?>
                        </a>
                        <button type="button" id="mxch-cg-chat-toggle" class="mxch-cg-action-btn" title="<?php esc_attr_e('Edit with AI', 'mxchat'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            <?php esc_html_e('AI Edit', 'mxchat'); ?>
                        </button>
                    </div>
                </div>

                <!-- Full-Width Preview -->
                <div class="mxch-cg-preview-wrap">
                    <div id="mxch-cg-preview-container" class="mxch-cg-preview-container">
                        <!-- Inline Generation Form -->
                        <div id="mxch-cg-inline-form" class="mxch-cg-inline-form">
                            <div class="mxch-cg-inline-form-inner">
                                <div class="mxch-cg-inline-form-header">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                    <div>
                                        <h3><?php esc_html_e('Generate Content', 'mxchat'); ?></h3>
                                        <p><?php esc_html_e('Describe what you want and let AI create it for you.', 'mxchat'); ?></p>
                                    </div>
                                </div>
                                <div class="mxch-cg-form">
                                    <textarea id="mxch-cg-prompt" class="mxch-cg-prompt" rows="3" placeholder="<?php esc_attr_e('Describe the content you want to generate...', 'mxchat'); ?>"></textarea>
                                    <div class="mxch-cg-options">
                                        <div class="mxch-cg-option">
                                            <label class="mxch-cg-option-label" for="mxch-cg-type"><?php esc_html_e('Type', 'mxchat'); ?></label>
                                            <select id="mxch-cg-type" class="mxch-cg-select">
                                                <option value="post"><?php esc_html_e('Blog Post', 'mxchat'); ?></option>
                                                <option value="page"><?php esc_html_e('Landing Page', 'mxchat'); ?></option>
                                            </select>
                                        </div>
                                        <div class="mxch-cg-option">
                                            <label class="mxch-cg-option-label" for="mxch-cg-status"><?php esc_html_e('Status', 'mxchat'); ?></label>
                                            <select id="mxch-cg-status" class="mxch-cg-select">
                                                <option value="draft"><?php esc_html_e('Draft', 'mxchat'); ?></option>
                                                <option value="future"><?php esc_html_e('Scheduled', 'mxchat'); ?></option>
                                                <option value="publish"><?php esc_html_e('Publish', 'mxchat'); ?></option>
                                            </select>
                                        </div>
                                        <div class="mxch-cg-option mxch-cg-schedule-wrap" style="display:none;">
                                            <label class="mxch-cg-option-label" for="mxch-cg-schedule"><?php esc_html_e('Schedule', 'mxchat'); ?></label>
                                            <input type="datetime-local" id="mxch-cg-schedule" class="mxch-cg-input">
                                        </div>
                                    </div>
                                    <div class="mxch-cg-options">
                                        <div class="mxch-cg-option">
                                            <label class="mxch-cg-option-label" for="mxch-cg-layout"><?php esc_html_e('Layout', 'mxchat'); ?></label>
                                            <select id="mxch-cg-layout" class="mxch-cg-select">
                                                <option value="default"><?php esc_html_e('Theme Default', 'mxchat'); ?></option>
                                                <option value="fullwidth" selected><?php esc_html_e('Fullwidth (No Padding)', 'mxchat'); ?></option>
                                            </select>
                                        </div>
                                        <div class="mxch-cg-option">
                                            <label class="mxch-cg-option-label" for="mxch-cg-title-display"><?php esc_html_e('Post Title', 'mxchat'); ?></label>
                                            <select id="mxch-cg-title-display" class="mxch-cg-select">
                                                <option value="show"><?php esc_html_e('Show', 'mxchat'); ?></option>
                                                <option value="hide" selected><?php esc_html_e('Hide (title in content)', 'mxchat'); ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="button" id="mxch-cg-generate-btn" class="mxch-cg-generate-btn mxch-cg-generate-btn-full">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                        <span><?php esc_html_e('Generate Content', 'mxchat'); ?></span>
                                    </button>
                                </div>
                                <!-- Progress (shown during generation) -->
                                <div id="mxch-cg-progress" class="mxch-cg-progress" style="display:none;">
                                    <div class="mxch-cg-progress-bar">
                                        <div class="mxch-cg-progress-fill" style="width:0%"></div>
                                    </div>
                                    <p class="mxch-cg-progress-text"><?php esc_html_e('Starting...', 'mxchat'); ?></p>
                                </div>
                            </div>
                        </div>
                        <!-- Loading indicator (shown during async generation) -->
                        <div id="mxch-cg-loading-indicator" class="mxch-cg-loading-indicator" style="display:none;">
                            <div class="mxch-cg-loading-spinner">
                                <div class="mxch-cg-loading-spinner-ring"></div>
                                <div class="mxch-cg-loading-spinner-ring"></div>
                                <div class="mxch-cg-loading-spinner-ring"></div>
                                <svg class="mxch-cg-loading-spinner-icon" xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            </div>
                            <p id="mxch-cg-loading-phrase" class="mxch-cg-loading-phrase"></p>
                            <div class="mxch-cg-loading-progress-mini">
                                <div class="mxch-cg-loading-progress-mini-bar">
                                    <div id="mxch-cg-loading-progress-fill" class="mxch-cg-loading-progress-mini-fill" style="width:0%"></div>
                                </div>
                                <p id="mxch-cg-loading-progress-text" class="mxch-cg-loading-progress-mini-text"><?php esc_html_e('Starting...', 'mxchat'); ?></p>
                            </div>
                        </div>
                        <iframe id="mxch-cg-preview-iframe" class="mxch-cg-preview-iframe" style="display:none;" sandbox="allow-same-origin allow-scripts"></iframe>
                    </div>
                </div>

                <!-- Mini Chat Panel (slides up from bottom, hidden by default) -->
                <div id="mxch-cg-chat" class="mxch-cg-chat-panel mxch-cg-chat-panel--with-images" style="display:none;">

                    <!-- Left Column: Images + Meta (Tabbed) -->
                    <div class="mxch-cg-images-col<?php echo $pro_image_management ? '' : ' mxch-cg-pro-locked'; ?>">
                        <!-- Tab Navigation -->
                        <div class="mxch-cg-left-tabs">
                            <button type="button" class="mxch-cg-left-tab active" data-tab="images">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                <?php esc_html_e('Images', 'mxchat'); ?>
                                <?php if (!$pro_image_management): ?>
                                    <span class="mxch-cg-pro-badge"><?php echo $is_activated ? esc_html__('ADD-ON', 'mxchat') : esc_html__('PRO', 'mxchat'); ?></span>
                                <?php endif; ?>
                            </button>
                            <button type="button" class="mxch-cg-left-tab" data-tab="meta">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7V4h16v3"/><path d="M9 20h6"/><path d="M12 4v16"/></svg>
                                <?php esc_html_e('Meta', 'mxchat'); ?>
                                <?php if (!$pro_meta_management): ?>
                                    <span class="mxch-cg-pro-badge"><?php echo $is_activated ? esc_html__('ADD-ON', 'mxchat') : esc_html__('PRO', 'mxchat'); ?></span>
                                <?php endif; ?>
                            </button>
                        </div>

                        <!-- Images Panel (default visible) -->
                        <div class="mxch-cg-left-panel active" id="mxch-cg-panel-images">
                            <div class="mxch-cg-images-grid" id="mxch-cg-images-grid">
                                <div class="mxch-cg-images-empty" id="mxch-cg-images-empty">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                    <span><?php esc_html_e('Images will appear here after generation', 'mxchat'); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Meta Panel (hidden by default) -->
                        <div class="mxch-cg-left-panel" id="mxch-cg-panel-meta" style="position:relative;">
                            <div class="mxch-cg-meta-form<?php echo $pro_meta_management ? '' : ' mxch-cg-meta-locked'; ?>">
                                <div class="mxch-cg-meta-field">
                                    <label for="mxch-cg-meta-title"><?php esc_html_e('Title', 'mxchat'); ?></label>
                                    <input type="text" id="mxch-cg-meta-title" class="mxch-cg-meta-input" placeholder="<?php esc_attr_e('Post title...', 'mxchat'); ?>"<?php echo $pro_meta_management ? '' : ' disabled readonly'; ?>>
                                </div>
                                <div class="mxch-cg-meta-field">
                                    <label for="mxch-cg-meta-description"><?php esc_html_e('Meta Description', 'mxchat'); ?></label>
                                    <textarea id="mxch-cg-meta-description" class="mxch-cg-meta-input" rows="3" placeholder="<?php esc_attr_e('Meta description...', 'mxchat'); ?>"<?php echo $pro_meta_management ? '' : ' disabled readonly'; ?>></textarea>
                                    <span class="mxch-cg-meta-charcount">0 / 160</span>
                                </div>
                                <div class="mxch-cg-meta-field">
                                    <label for="mxch-cg-meta-keyword"><?php esc_html_e('Focus Keyword', 'mxchat'); ?></label>
                                    <input type="text" id="mxch-cg-meta-keyword" class="mxch-cg-meta-input" placeholder="<?php esc_attr_e('Primary keyword...', 'mxchat'); ?>"<?php echo $pro_meta_management ? '' : ' disabled readonly'; ?>>
                                </div>
                                <div class="mxch-cg-meta-field">
                                    <label for="mxch-cg-meta-excerpt"><?php esc_html_e('Excerpt', 'mxchat'); ?></label>
                                    <textarea id="mxch-cg-meta-excerpt" class="mxch-cg-meta-input" rows="3" placeholder="<?php esc_attr_e('Post excerpt...', 'mxchat'); ?>"<?php echo $pro_meta_management ? '' : ' disabled readonly'; ?>></textarea>
                                </div>
                            </div>
                            <?php if (!$pro_meta_management): ?>
                            <div class="mxch-cg-meta-lock-overlay">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                <span><?php echo $is_activated ? esc_html__('Install Add-on', 'mxchat') : esc_html__('Upgrade to Pro', 'mxchat'); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Chat Column -->
                    <div class="mxch-cg-chat-col">
                        <div class="mxch-cg-chat-panel-header">
                            <span class="mxch-cg-chat-panel-title"><?php esc_html_e('Edit with AI', 'mxchat'); ?></span>
                            <button type="button" id="mxch-cg-chat-close" class="mxch-cg-chat-panel-close" title="<?php esc_attr_e('Close', 'mxchat'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>
                        <div id="mxch-cg-chat-messages" class="mxch-cg-chat-messages"></div>
                        <div class="mxch-cg-chat-input-wrap">
                            <textarea id="mxch-cg-chat-input" class="mxch-cg-chat-input" rows="2" placeholder="<?php esc_attr_e('e.g. Change the hero heading to...', 'mxchat'); ?>"></textarea>
                            <button type="button" id="mxch-cg-chat-send" class="mxch-cg-chat-send" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 HISTORY
                 ======================================== -->
            <div id="content-history" class="mxch-section">
                <div class="mxch-content-header">
                    <h1 class="mxch-content-title"><?php esc_html_e('Content History', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Browse and edit your AI-generated content.', 'mxchat'); ?></p>
                </div>

                <div class="mxch-card">
                    <div class="mxch-card-body">
                        <!-- Loading state -->
                        <div id="mxch-cg-history-loading" class="mxch-cg-history-loading">
                            <div class="mxch-cg-history-spinner"></div>
                            <p><?php esc_html_e('Loading history...', 'mxchat'); ?></p>
                        </div>

                        <!-- Empty state -->
                        <div id="mxch-cg-history-empty" class="mxch-cg-history-empty" style="display:none;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <p><?php esc_html_e('No AI-generated content yet. Use the Generate tab to create your first post.', 'mxchat'); ?></p>
                        </div>

                        <!-- History list (rendered by JS) -->
                        <div id="mxch-cg-history-list" class="mxch-cg-history-list" style="display:none;"></div>

                        <!-- Pagination (rendered by JS) -->
                        <div id="mxch-cg-history-pagination" class="mxch-cg-history-pagination" style="display:none;"></div>
                    </div>
                </div>
            </div>

            <!-- ========================================
                 SETTINGS
                 ======================================== -->
            <div id="content-settings" class="mxch-section">
                <div class="mxch-content-header">
                    <h1 class="mxch-content-title"><?php esc_html_e('Content Settings', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Configure AI models and image generation for content creation.', 'mxchat'); ?></p>
                </div>

                <div class="mxch-card">
                    <div class="mxch-card-body">

                        <!-- Content Model -->
                        <div class="mxch-field">
                            <label class="mxch-field-label" for="mxch-content-model"><?php esc_html_e('Content Model', 'mxchat'); ?></label>
                            <div class="mxch-field-control">
                                <select id="mxch-content-model" data-field="content_model" class="mxch-cg-select">
                                    <?php
                                    $content_model = $options['content_model'] ?? ($options['model'] ?? 'gpt-5.1-chat-latest');
                                    $models = array(
                                        'OpenAI' => array(
                                            'gpt-5.2'              => 'GPT-5.2',
                                            'gpt-5.1-chat-latest'  => 'GPT-5.1',
                                            'gpt-5'                => 'GPT-5',
                                        ),
                                        'Anthropic' => array(
                                            'claude-opus-4-6'              => 'Claude Opus 4.6',
                                            'claude-sonnet-4-6'            => 'Claude Sonnet 4.6',
                                            'claude-haiku-4-5-20251001'    => 'Claude Haiku 4.5',
                                        ),
                                        'Google' => array(
                                            'gemini-3-pro-preview' => 'Gemini 3 Pro',
                                            'gemini-2.5-pro'       => 'Gemini 2.5 Pro',
                                        ),
                                        'xAI' => array(
                                            'grok-4-0709'                  => 'Grok 4',
                                            'grok-4-1-fast-non-reasoning'  => 'Grok 4.1 Fast',
                                            'grok-code-fast-1'             => 'Grok Code Fast',
                                        ),
                                    );
                                    foreach ($models as $group => $group_models) {
                                        echo '<optgroup label="' . esc_attr($group) . '">';
                                        foreach ($group_models as $value => $label) {
                                            $selected = selected($content_model, $value, false);
                                            echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
                                        }
                                        echo '</optgroup>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <p class="mxch-field-description"><?php esc_html_e('Select the AI model used for generating content. Uses the API key configured in your main MxChat settings.', 'mxchat'); ?></p>
                        </div>

                        <!-- Image Model -->
                        <div class="mxch-field">
                            <label class="mxch-field-label" for="mxch-image-model"><?php esc_html_e('Image Model', 'mxchat'); ?></label>
                            <div class="mxch-field-control">
                                <?php $image_model = $options['content_image_model'] ?? 'gpt-image-1.5'; ?>
                                <select id="mxch-image-model" data-field="content_image_model" class="mxch-cg-select">
                                    <optgroup label="<?php esc_attr_e('OpenAI', 'mxchat'); ?>">
                                        <option value="gpt-image-1.5" <?php selected($image_model, 'gpt-image-1.5'); ?>><?php esc_html_e('GPT Image 1.5', 'mxchat'); ?></option>
                                    </optgroup>
                                    <optgroup label="<?php esc_attr_e('xAI', 'mxchat'); ?>">
                                        <option value="grok-imagine-image-pro" <?php selected($image_model, 'grok-imagine-image-pro'); ?>><?php esc_html_e('Grok Imagine Pro', 'mxchat'); ?></option>
                                        <option value="grok-imagine-image" <?php selected($image_model, 'grok-imagine-image'); ?>><?php esc_html_e('Grok Imagine', 'mxchat'); ?></option>
                                    </optgroup>
                                    <optgroup label="<?php esc_attr_e('Google', 'mxchat'); ?>">
                                        <option value="gemini-2.5-flash-image" <?php selected($image_model, 'gemini-2.5-flash-image'); ?>><?php esc_html_e('Nano Banana (Gemini 2.5 Flash)', 'mxchat'); ?></option>
                                        <option value="gemini-3-pro-image-preview" <?php selected($image_model, 'gemini-3-pro-image-preview'); ?>><?php esc_html_e('Nano Banana Pro (Gemini 3 Pro)', 'mxchat'); ?></option>
                                    </optgroup>
                                </select>
                            </div>
                            <p class="mxch-field-description"><?php esc_html_e('Select the AI model used for generating images within your content.', 'mxchat'); ?></p>
                        </div>

                        <!-- Enable Image Generation -->
                        <div class="mxch-field">
                            <label class="mxch-toggle">
                                <?php $enable_images = $options['content_enable_images'] ?? 'on'; ?>
                                <input type="checkbox" class="mxch-toggle-input" data-field="content_enable_images" value="on" <?php checked($enable_images, 'on'); ?>>
                                <span class="mxch-toggle-switch"></span>
                                <span class="mxch-toggle-label"><?php esc_html_e('Enable Image Generation', 'mxchat'); ?></span>
                            </label>
                            <p class="mxch-field-description"><?php esc_html_e('When enabled, AI will generate and place images throughout your content. When disabled, pages are generated with text-only layouts.', 'mxchat'); ?></p>
                        </div>

                        <!-- Internal Linking (Pro) -->
                        <div class="mxch-field mxch-cg-pro-field<?php echo $pro_internal_linking ? '' : ' mxch-cg-pro-locked'; ?>">
                            <label class="mxch-toggle">
                                <?php $internal_linking_val = $options['content_internal_linking'] ?? 'off'; ?>
                                <input type="checkbox" class="mxch-toggle-input" data-field="content_internal_linking" value="on" <?php checked($internal_linking_val, 'on'); ?><?php echo $pro_internal_linking ? '' : ' disabled'; ?>>
                                <span class="mxch-toggle-switch"></span>
                                <span class="mxch-toggle-label">
                                    <?php esc_html_e('Internal Linking', 'mxchat'); ?>
                                    <?php if (!$pro_internal_linking): ?>
                                        <span class="mxch-cg-pro-badge"><?php echo $is_activated ? esc_html__('ADD-ON', 'mxchat') : esc_html__('PRO', 'mxchat'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </label>
                            <p class="mxch-field-description"><?php esc_html_e('Automatically find and link to existing blog posts on your site within generated content.', 'mxchat'); ?></p>
                            <?php if (!$pro_internal_linking): ?>
                                <?php if ($is_activated): ?>
                                    <a href="https://mxchat.ai/" target="_blank" class="mxch-cg-pro-upgrade-link"><?php esc_html_e('Install Add-on', 'mxchat'); ?></a>
                                <?php else: ?>
                                    <a href="https://mxchat.ai/" target="_blank" class="mxch-cg-pro-upgrade-link"><?php esc_html_e('Upgrade to Pro', 'mxchat'); ?></a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Tool Use for AI Editing (Pro) -->
                        <div class="mxch-field mxch-cg-pro-field<?php echo $pro_tool_use ? '' : ' mxch-cg-pro-locked'; ?>">
                            <label class="mxch-toggle">
                                <?php $tool_use_val = $options['content_tool_use'] ?? 'off'; ?>
                                <input type="checkbox" class="mxch-toggle-input" data-field="content_tool_use" value="on" <?php checked($tool_use_val, 'on'); ?><?php echo $pro_tool_use ? '' : ' disabled'; ?>>
                                <span class="mxch-toggle-switch"></span>
                                <span class="mxch-toggle-label">
                                    <?php esc_html_e('Tool Use for AI Editing', 'mxchat'); ?>
                                    <?php if (!$pro_tool_use): ?>
                                        <span class="mxch-cg-pro-badge"><?php echo $is_activated ? esc_html__('ADD-ON', 'mxchat') : esc_html__('PRO', 'mxchat'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </label>
                            <p class="mxch-field-description"><?php esc_html_e('Enable precise editing with tool calling — the AI makes targeted changes instead of rewriting the entire page, improving accuracy and token efficiency. Supports Claude, OpenAI, Gemini, and xAI models.', 'mxchat'); ?></p>
                            <?php if (!$pro_tool_use): ?>
                                <?php if ($is_activated): ?>
                                    <a href="https://mxchat.ai/" target="_blank" class="mxch-cg-pro-upgrade-link"><?php esc_html_e('Install Add-on', 'mxchat'); ?></a>
                                <?php else: ?>
                                    <a href="https://mxchat.ai/" target="_blank" class="mxch-cg-pro-upgrade-link"><?php esc_html_e('Upgrade to Pro', 'mxchat'); ?></a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>

        </main>
    </div>
    <?php
}
