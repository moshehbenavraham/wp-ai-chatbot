<?php
/**
 * MxChat Actions Page - Redesigned with Sidebar Navigation
 *
 * @package MxChat
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the new sidebar-based Actions page
 */
function mxchat_render_actions_page($admin_instance, $page_data) {
    $is_activated = $admin_instance->is_activated();
    $plugin_url = plugin_dir_url(dirname(__FILE__));

    // Extract page data
    extract($page_data);
    ?>
    <div class="mxch-admin-wrapper mxch-actions-wrapper">
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
                <span class="mxch-mobile-menu-title"><?php esc_html_e('Actions', 'mxchat'); ?></span>
                <button type="button" class="mxch-mobile-menu-close" aria-label="<?php esc_attr_e('Close menu', 'mxchat'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <nav class="mxch-mobile-menu-nav">
                <!-- Overview Section -->
                <div class="mxch-mobile-nav-section">
                    <div class="mxch-mobile-nav-section-title"><?php esc_html_e('Overview', 'mxchat'); ?></div>
                    <button class="mxch-mobile-nav-link active" data-target="dashboard">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                        <span><?php esc_html_e('Dashboard', 'mxchat'); ?></span>
                    </button>
                </div>
                <!-- Actions Section -->
                <div class="mxch-mobile-nav-section">
                    <div class="mxch-mobile-nav-section-title"><?php esc_html_e('Manage', 'mxchat'); ?></div>
                    <button class="mxch-mobile-nav-link" data-target="all-actions">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        <span><?php esc_html_e('All Actions', 'mxchat'); ?></span>
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
                <!-- Overview Section -->
                <div class="mxch-nav-section">
                    <div class="mxch-nav-section-title"><?php esc_html_e('Overview', 'mxchat'); ?></div>

                    <div class="mxch-nav-item" data-section="dashboard">
                        <button class="mxch-nav-link active" data-target="dashboard">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('Dashboard', 'mxchat'); ?></span>
                        </button>
                    </div>
                </div>

                <!-- Manage Section -->
                <div class="mxch-nav-section">
                    <div class="mxch-nav-section-title"><?php esc_html_e('Manage', 'mxchat'); ?></div>

                    <div class="mxch-nav-item" data-section="all-actions">
                        <button class="mxch-nav-link" data-target="all-actions">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('All Actions', 'mxchat'); ?></span>
                            <span class="mxch-nav-link-badge"><?php echo esc_html($total_actions); ?></span>
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
            <!-- Dashboard Section -->
            <div id="dashboard" class="mxch-section active">
                <div class="mxch-content-header">
                    <h1 class="mxch-content-title"><?php esc_html_e('Actions Dashboard', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Create and manage custom actions to enhance your chatbot\'s capabilities.', 'mxchat'); ?></p>
                </div>

                <!-- Quick Actions Card -->
                <div class="mxch-card">
                    <div class="mxch-card-header">
                        <h3 class="mxch-card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            <?php esc_html_e('Quick Actions', 'mxchat'); ?>
                        </h3>
                    </div>
                    <div class="mxch-card-body">
                        <div class="mxch-quick-actions">
                            <button type="button" class="mxch-quick-action-btn" id="mxch-add-action-dashboard-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                                <span><?php esc_html_e('Add New Action', 'mxchat'); ?></span>
                            </button>
                            <button type="button" class="mxch-quick-action-btn" data-action="view-actions">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                <span><?php esc_html_e('View All Actions', 'mxchat'); ?></span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- What are Actions - Info Section -->
                <div class="mxch-info-section">
                    <div class="mxch-info-section-header">
                        <div class="mxch-info-section-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        </div>
                        <h3 class="mxch-info-section-title"><?php esc_html_e('What are Actions?', 'mxchat'); ?></h3>
                    </div>
                    <p class="mxch-info-section-desc">
                        <?php esc_html_e('Actions let your chatbot do more than just chat. When a visitor\'s message is similar in meaning to your trigger phrase, the bot automatically performs the action - like sending emails, redirecting pages, or displaying content.', 'mxchat'); ?>
                    </p>
                    <div class="mxch-info-features">
                        <div class="mxch-info-feature">
                            <div class="mxch-info-feature-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                            </div>
                            <div class="mxch-info-feature-content">
                                <span class="mxch-info-feature-title"><?php esc_html_e('Meaning-Based Matching', 'mxchat'); ?></span>
                                <span class="mxch-info-feature-desc"><?php esc_html_e('Uses AI to understand intent, not just exact words. "I want to buy" matches "purchase" or "order"', 'mxchat'); ?></span>
                            </div>
                        </div>
                        <div class="mxch-info-feature">
                            <div class="mxch-info-feature-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
                            </div>
                            <div class="mxch-info-feature-content">
                                <span class="mxch-info-feature-title"><?php esc_html_e('Similarity Threshold', 'mxchat'); ?></span>
                                <span class="mxch-info-feature-desc"><?php esc_html_e('Set how closely a message must match. Higher = more precise, Lower = more flexible', 'mxchat'); ?></span>
                            </div>
                        </div>
                        <div class="mxch-info-feature">
                            <div class="mxch-info-feature-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><path d="m9 15 2 2 4-4"/></svg>
                            </div>
                            <div class="mxch-info-feature-content">
                                <span class="mxch-info-feature-title"><?php esc_html_e('Best Practices', 'mxchat'); ?></span>
                                <span class="mxch-info-feature-desc"><?php esc_html_e('Use natural phrases visitors would say. Make triggers distinct from each other to avoid overlap', 'mxchat'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- All Actions Section - Split Panel Layout -->
            <div id="all-actions" class="mxch-section">
                <div class="mxch-split-panel">
                    <!-- Left Panel - Action List -->
                    <div class="mxch-action-list-panel">
                        <!-- Bulk Actions Toolbar -->
                        <div class="mxch-bulk-toolbar">
                            <label class="mxch-bulk-select-all">
                                <input type="checkbox" id="mxch-select-all-actions">
                                <span><?php esc_html_e('All', 'mxchat'); ?></span>
                            </label>
                            <span class="mxch-selected-count" id="mxch-selected-action-count">0</span>
                            <div class="mxch-bulk-actions">
                                <button type="button" class="mxch-bulk-btn mxch-bulk-delete" id="mxch-delete-selected-actions" disabled title="<?php esc_attr_e('Delete Selected', 'mxchat'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="mxch-panel-header">
                            <div class="mxch-search-wrapper">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="text" id="mxch-search-actions" class="mxch-search-input" placeholder="<?php esc_attr_e('Search actions...', 'mxchat'); ?>">
                            </div>
                            <div class="mxch-panel-title-row">
                                <span class="mxch-panel-count" id="mxch-action-count">0 / 0 actions</span>
                                <div class="mxch-panel-actions">
                                    <button type="button" id="mxch-add-action-btn" class="mxch-btn mxch-btn-primary mxch-btn-sm" title="<?php esc_attr_e('Add New Action', 'mxchat'); ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                        <?php esc_html_e('Add', 'mxchat'); ?>
                                    </button>
                                    <button type="button" id="mxch-refresh-actions" class="mxch-icon-btn" title="<?php esc_attr_e('Refresh', 'mxchat'); ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="mxch-action-list" id="mxch-action-list">
                            <!-- Action list items loaded via AJAX -->
                        </div>
                        <div class="mxch-panel-footer">
                            <div class="mxch-pagination-simple" id="mxch-actions-pagination">
                                <!-- Pagination controls -->
                            </div>
                        </div>
                    </div>

                    <!-- Right Panel - Action Details/Editor -->
                    <div class="mxch-action-detail-panel" id="mxch-action-detail-panel">
                        <!-- Empty State -->
                        <div class="mxch-action-empty" id="mxch-action-empty">
                            <div class="mxch-empty-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            </div>
                            <h3><?php esc_html_e('Select an action', 'mxchat'); ?></h3>
                            <p><?php esc_html_e('Choose an action from the list to view details and edit, or create a new one.', 'mxchat'); ?></p>
                            <button type="button" id="mxch-create-first-action-btn" class="mxch-btn mxch-btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                <?php esc_html_e('Create New Action', 'mxchat'); ?>
                            </button>
                        </div>

                        <!-- Action Editor (hidden initially) -->
                        <div class="mxch-action-editor" id="mxch-action-editor" style="display: none;">
                            <!-- Step 1: Action Type Selection -->
                            <div id="mxch-editor-step-1" class="mxch-editor-step active">
                                <div class="mxch-editor-header">
                                    <button type="button" class="mxch-mobile-back-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                                        <?php esc_html_e('Back', 'mxchat'); ?>
                                    </button>
                                    <h3 class="mxch-editor-title"><?php esc_html_e('Select Action Type', 'mxchat'); ?></h3>
                                    <button type="button" class="mxch-editor-close" id="mxch-close-editor">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                </div>

                                <div class="mxch-type-search">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                    <input type="text" id="mxch-type-search-input" placeholder="<?php esc_attr_e('Search action types...', 'mxchat'); ?>">
                                </div>

                                <div class="mxch-type-categories">
                                    <button type="button" class="mxch-category-btn active" data-category="all"><?php esc_html_e('All', 'mxchat'); ?></button>
                                    <?php foreach ($callback_groups as $group_label => $group_callbacks): ?>
                                        <button type="button" class="mxch-category-btn" data-category="<?php echo esc_attr(sanitize_title($group_label)); ?>"><?php echo esc_html($group_label); ?></button>
                                    <?php endforeach; ?>
                                </div>

                                <div class="mxch-types-grid" id="mxch-types-grid">
                                    <?php foreach ($callback_groups as $group_label => $group_callbacks):
                                        $category_slug = sanitize_title($group_label);
                                        foreach ($group_callbacks as $function => $data):
                                            $label = $data['label'];
                                            $pro_only = $data['pro_only'];
                                            $icon = isset($data['icon']) ? $data['icon'] : 'admin-generic';
                                            $description = isset($data['description']) ? $data['description'] : '';
                                            $is_addon = isset($data['addon']) && $data['addon'] !== false;
                                            $addon_name = isset($data['addon_name']) ? $data['addon_name'] : '';
                                            $is_installed = isset($data['installed']) ? $data['installed'] : true;
                                    ?>
                                        <div class="mxch-type-card <?php echo (!$is_activated && $pro_only) ? 'mxch-type-pro' : ''; ?> <?php echo ($is_addon && !$is_installed) ? 'mxch-type-addon' : ''; ?>"
                                             data-category="<?php echo esc_attr($category_slug); ?>"
                                             data-value="<?php echo esc_attr($function); ?>"
                                             data-label="<?php echo esc_attr($label); ?>"
                                             data-pro="<?php echo $pro_only ? 'true' : 'false'; ?>"
                                             data-addon="<?php echo esc_attr($is_addon ? $data['addon'] : ''); ?>"
                                             data-installed="<?php echo $is_installed ? 'true' : 'false'; ?>">
                                            <div class="mxch-type-icon">
                                                <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>"></span>
                                            </div>
                                            <div class="mxch-type-info">
                                                <h4><?php echo esc_html($label); ?></h4>
                                                <p><?php echo esc_html($description ?: sprintf(__('Use the %s action', 'mxchat'), $label)); ?></p>
                                                <?php if ($pro_only && !$is_activated): ?>
                                                    <span class="mxch-badge mxch-badge-pro"><?php esc_html_e('Pro', 'mxchat'); ?></span>
                                                <?php endif; ?>
                                                <?php if ($is_addon && !$is_installed): ?>
                                                    <span class="mxch-badge mxch-badge-addon"><?php echo esc_html(sprintf(__('Requires %s', 'mxchat'), $addon_name)); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; endforeach; ?>
                                </div>
                            </div>

                            <!-- Step 2: Action Configuration -->
                            <div id="mxch-editor-step-2" class="mxch-editor-step">
                                <div class="mxch-editor-header">
                                    <button type="button" class="mxch-back-btn" id="mxch-back-to-step-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                                        <?php esc_html_e('Back', 'mxchat'); ?>
                                    </button>
                                    <h3 class="mxch-editor-title" id="mxch-config-title"><?php esc_html_e('Configure Action', 'mxchat'); ?></h3>
                                    <button type="button" class="mxch-editor-close" id="mxch-close-editor-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                </div>

                                <div class="mxch-selected-type">
                                    <div class="mxch-selected-type-icon" id="mxch-selected-type-icon">
                                        <span class="dashicons dashicons-admin-generic"></span>
                                    </div>
                                    <div class="mxch-selected-type-info">
                                        <h4 id="mxch-selected-type-label"><?php esc_html_e('Selected Action', 'mxchat'); ?></h4>
                                        <p id="mxch-selected-type-desc"><?php esc_html_e('Configure this action for your chatbot', 'mxchat'); ?></p>
                                    </div>
                                </div>

                                <form id="mxch-action-form" method="post">
                                    <?php wp_nonce_field('mxchat_add_intent_nonce', 'mxch_action_nonce'); ?>
                                    <input type="hidden" name="action_id" id="mxch-action-id" value="">
                                    <input type="hidden" name="callback_function" id="mxch-callback-function" value="">
                                    <input type="hidden" name="form_mode" id="mxch-form-mode" value="add">

                                    <div class="mxch-form-group">
                                        <label for="mxch-action-label"><?php esc_html_e('Action Label', 'mxchat'); ?></label>
                                        <input type="text" id="mxch-action-label" name="intent_label" required placeholder="<?php esc_attr_e('e.g., Newsletter Signup', 'mxchat'); ?>">
                                        <p class="mxch-form-hint"><?php esc_html_e('A descriptive name for this action (for your reference only).', 'mxchat'); ?></p>
                                    </div>

                                    <div class="mxch-form-group">
                                        <label for="mxch-action-phrases"><?php esc_html_e('Trigger Phrases', 'mxchat'); ?></label>
                                        <textarea id="mxch-action-phrases" name="phrases" rows="4" required placeholder="<?php esc_attr_e('sign me up, subscribe me, I want to join, add me to the newsletter', 'mxchat'); ?>"></textarea>
                                        <p class="mxch-form-hint"><?php esc_html_e('Comma-separated phrases that will trigger this action.', 'mxchat'); ?></p>
                                    </div>

                                    <div class="mxch-form-group">
                                        <label for="mxch-action-threshold">
                                            <?php esc_html_e('Similarity Threshold', 'mxchat'); ?>
                                            <span class="mxch-threshold-value" id="mxch-threshold-value">85%</span>
                                        </label>
                                        <input type="range" id="mxch-action-threshold" name="similarity_threshold" min="10" max="95" value="85">
                                        <p class="mxch-form-hint"><?php esc_html_e('Lower values (10-30) trigger more easily. Higher values (70-95) require more exact matches.', 'mxchat'); ?></p>
                                    </div>

                                    <div class="mxch-form-group">
                                        <label><?php esc_html_e('Enabled Bots', 'mxchat'); ?></label>
                                        <div class="mxch-bot-selector" id="mxch-bot-selector">
                                            <label class="mxch-checkbox-label">
                                                <input type="checkbox" name="enabled_bots[]" value="default" checked>
                                                <span class="mxch-checkmark"></span>
                                                <?php esc_html_e('Default Bot', 'mxchat'); ?>
                                            </label>
                                            <?php if (class_exists('MxChat_Multi_Bot_Core_Manager')):
                                                $multi_bot_manager = MxChat_Multi_Bot_Core_Manager::get_instance();
                                                $available_bots_list = $multi_bot_manager->get_available_bots();
                                                foreach ($available_bots_list as $bot_id => $bot_name):
                                                    if ($bot_id === 'default') continue;
                                            ?>
                                                <label class="mxch-checkbox-label">
                                                    <input type="checkbox" name="enabled_bots[]" value="<?php echo esc_attr($bot_id); ?>">
                                                    <span class="mxch-checkmark"></span>
                                                    <?php echo esc_html($bot_name); ?>
                                                </label>
                                            <?php endforeach; endif; ?>
                                        </div>
                                    </div>

                                    <div class="mxch-form-actions">
                                        <button type="button" class="mxch-btn mxch-btn-secondary" id="mxch-cancel-action">
                                            <?php esc_html_e('Cancel', 'mxchat'); ?>
                                        </button>
                                        <button type="submit" class="mxch-btn mxch-btn-primary" id="mxch-save-action">
                                            <?php esc_html_e('Save Action', 'mxchat'); ?>
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Action View (for viewing existing action details) -->
                            <div id="mxch-action-view" class="mxch-editor-step">
                                <div class="mxch-editor-header">
                                    <button type="button" class="mxch-mobile-back-btn">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                                        <?php esc_html_e('Back', 'mxchat'); ?>
                                    </button>
                                    <h3 class="mxch-editor-title" id="mxch-view-title"><?php esc_html_e('Action Details', 'mxchat'); ?></h3>
                                    <div class="mxch-header-actions">
                                        <label class="mxch-toggle-switch" title="<?php esc_attr_e('Toggle Enabled', 'mxchat'); ?>">
                                            <input type="checkbox" id="mxch-action-enabled-toggle" checked>
                                            <span class="mxch-toggle-slider"></span>
                                        </label>
                                        <button type="button" class="mxch-icon-btn" id="mxch-edit-action-btn" title="<?php esc_attr_e('Edit', 'mxchat'); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                        <button type="button" class="mxch-icon-btn mxch-btn-danger-icon" id="mxch-delete-action-btn" title="<?php esc_attr_e('Delete', 'mxchat'); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="mxch-action-details">
                                    <div class="mxch-detail-card">
                                        <div class="mxch-detail-icon" id="mxch-view-icon">
                                            <span class="dashicons dashicons-admin-generic"></span>
                                        </div>
                                        <div class="mxch-detail-content">
                                            <h4 id="mxch-view-label"><?php esc_html_e('Action Label', 'mxchat'); ?></h4>
                                            <p class="mxch-detail-type" id="mxch-view-type"><?php esc_html_e('Action Type', 'mxchat'); ?></p>
                                        </div>
                                    </div>

                                    <div class="mxch-detail-section">
                                        <h5><?php esc_html_e('Trigger Phrases', 'mxchat'); ?></h5>
                                        <div class="mxch-phrases-list" id="mxch-view-phrases">
                                            <!-- Phrases loaded dynamically -->
                                        </div>
                                    </div>

                                    <div class="mxch-detail-section">
                                        <h5><?php esc_html_e('Settings', 'mxchat'); ?></h5>
                                        <div class="mxch-settings-grid">
                                            <div class="mxch-setting-item">
                                                <span class="mxch-setting-label"><?php esc_html_e('Similarity Threshold', 'mxchat'); ?></span>
                                                <span class="mxch-setting-value" id="mxch-view-threshold">85%</span>
                                            </div>
                                            <div class="mxch-setting-item">
                                                <span class="mxch-setting-label"><?php esc_html_e('Status', 'mxchat'); ?></span>
                                                <span class="mxch-setting-value mxch-status-badge" id="mxch-view-status"><?php esc_html_e('Enabled', 'mxchat'); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mxch-detail-section">
                                        <h5><?php esc_html_e('Assigned Bots', 'mxchat'); ?></h5>
                                        <div class="mxch-bots-list" id="mxch-view-bots">
                                            <!-- Bots loaded dynamically -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Loading Overlay -->
    <div id="mxch-action-loading" class="mxch-loading-overlay" style="display: none;">
        <div class="mxch-loading-spinner"></div>
        <div class="mxch-loading-text"><?php esc_html_e('Saving action, please wait...', 'mxchat'); ?></div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="mxch-delete-modal" class="mxch-modal-overlay" style="display: none;">
        <div class="mxch-modal-content mxch-modal-sm">
            <div class="mxch-modal-header">
                <h2><?php esc_html_e('Delete Action', 'mxchat'); ?></h2>
                <button type="button" class="mxch-modal-close">&times;</button>
            </div>
            <div class="mxch-modal-body">
                <p><?php esc_html_e('Are you sure you want to delete this action? This cannot be undone.', 'mxchat'); ?></p>
            </div>
            <div class="mxch-modal-footer">
                <button type="button" class="mxch-btn mxch-btn-secondary" id="mxch-cancel-delete"><?php esc_html_e('Cancel', 'mxchat'); ?></button>
                <button type="button" class="mxch-btn mxch-btn-danger" id="mxch-confirm-delete"><?php esc_html_e('Delete', 'mxchat'); ?></button>
            </div>
        </div>
    </div>

    <?php
}
