<?php
/**
 * MxChat Chat Transcripts Page - Redesigned with Sidebar Navigation
 *
 * @package MxChat
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the new sidebar-based Transcripts page
 */
function mxchat_render_transcripts_page($admin_instance, $page_data) {
    $is_activated = $admin_instance->is_activated();
    $plugin_url = plugin_dir_url(dirname(__FILE__));

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
                <span class="mxch-mobile-menu-title"><?php esc_html_e('Transcripts', 'mxchat'); ?></span>
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
                <!-- Conversations Section -->
                <div class="mxch-mobile-nav-section">
                    <div class="mxch-mobile-nav-section-title"><?php esc_html_e('Conversations', 'mxchat'); ?></div>
                    <button class="mxch-mobile-nav-link" data-target="all-chats">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        <span><?php esc_html_e('All Chats', 'mxchat'); ?></span>
                    </button>
                </div>
                <!-- Settings Section -->
                <div class="mxch-mobile-nav-section">
                    <div class="mxch-mobile-nav-section-title"><?php esc_html_e('Settings', 'mxchat'); ?></div>
                    <button class="mxch-mobile-nav-link" data-target="notifications">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <span><?php esc_html_e('Notifications', 'mxchat'); ?></span>
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

                <!-- Conversations Section -->
                <div class="mxch-nav-section">
                    <div class="mxch-nav-section-title"><?php esc_html_e('Conversations', 'mxchat'); ?></div>

                    <div class="mxch-nav-item" data-section="all-chats">
                        <button class="mxch-nav-link" data-target="all-chats">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('All Chats', 'mxchat'); ?></span>
                            <span class="mxch-nav-link-badge"><?php echo esc_html($total_chats); ?></span>
                        </button>
                    </div>
                </div>

                <!-- Settings Section -->
                <div class="mxch-nav-section">
                    <div class="mxch-nav-section-title"><?php esc_html_e('Settings', 'mxchat'); ?></div>

                    <div class="mxch-nav-item" data-section="notifications">
                        <button class="mxch-nav-link" data-target="notifications">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('Notifications', 'mxchat'); ?></span>
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
                    <h1 class="mxch-content-title"><?php esc_html_e('Transcripts Dashboard', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Overview of your chatbot conversations and engagement metrics.', 'mxchat'); ?></p>
                </div>

                <!-- Stats Grid -->
                <div class="mxch-stats-grid">
                    <div class="mxch-stat-card">
                        <div class="mxch-stat-icon mxch-stat-icon-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <div class="mxch-stat-content">
                            <span class="mxch-stat-value"><?php echo esc_html($total_chats); ?></span>
                            <span class="mxch-stat-label"><?php esc_html_e('Total Chats', 'mxchat'); ?></span>
                        </div>
                    </div>

                    <div class="mxch-stat-card">
                        <div class="mxch-stat-icon mxch-stat-icon-success">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>
                        </div>
                        <div class="mxch-stat-content">
                            <span class="mxch-stat-value"><?php echo esc_html($total_messages); ?></span>
                            <span class="mxch-stat-label"><?php esc_html_e('Total Messages', 'mxchat'); ?></span>
                        </div>
                    </div>

                    <div class="mxch-stat-card">
                        <div class="mxch-stat-icon mxch-stat-icon-info">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <div class="mxch-stat-content">
                            <span class="mxch-stat-value"><?php echo esc_html($total_users); ?></span>
                            <span class="mxch-stat-label"><?php esc_html_e('Unique Users', 'mxchat'); ?></span>
                            <span class="mxch-stat-sublabel">
                                <?php
                                echo sprintf(
                                    esc_html__('%d registered, %d guests', 'mxchat'),
                                    $registered_users,
                                    $guest_users
                                );
                                ?>
                            </span>
                        </div>
                    </div>

                    <div class="mxch-stat-card">
                        <div class="mxch-stat-icon mxch-stat-icon-warning">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <div class="mxch-stat-content">
                            <span class="mxch-stat-value"><?php echo esc_html($avg_messages); ?></span>
                            <span class="mxch-stat-label"><?php esc_html_e('Avg Messages/Chat', 'mxchat'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Activity Cards Row -->
                <div class="mxch-cards-row">
                    <!-- Activity Timeline Card -->
                    <div class="mxch-card mxch-card-flex">
                        <div class="mxch-card-header">
                            <h3 class="mxch-card-title">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                                <?php esc_html_e('Recent Activity', 'mxchat'); ?>
                            </h3>
                        </div>
                        <div class="mxch-card-body">
                            <div class="mxch-activity-stats">
                                <div class="mxch-activity-item">
                                    <div class="mxch-activity-icon mxch-activity-today">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    </div>
                                    <div class="mxch-activity-content">
                                        <span class="mxch-activity-value"><?php echo esc_html($today_chats); ?></span>
                                        <span class="mxch-activity-label"><?php esc_html_e('Today', 'mxchat'); ?></span>
                                    </div>
                                </div>
                                <div class="mxch-activity-item">
                                    <div class="mxch-activity-icon mxch-activity-week">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    </div>
                                    <div class="mxch-activity-content">
                                        <span class="mxch-activity-value"><?php echo esc_html($week_chats); ?></span>
                                        <span class="mxch-activity-label"><?php esc_html_e('Last 7 Days', 'mxchat'); ?></span>
                                    </div>
                                </div>
                                <div class="mxch-activity-item">
                                    <div class="mxch-activity-icon mxch-activity-month">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                    </div>
                                    <div class="mxch-activity-content">
                                        <span class="mxch-activity-value"><?php echo esc_html($month_chats); ?></span>
                                        <span class="mxch-activity-label"><?php esc_html_e('Last 30 Days', 'mxchat'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Insights Card -->
                    <div class="mxch-card mxch-card-flex">
                        <div class="mxch-card-header">
                            <h3 class="mxch-card-title">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                <?php esc_html_e('Insights', 'mxchat'); ?>
                            </h3>
                        </div>
                        <div class="mxch-card-body">
                            <div class="mxch-insights-list">
                                <div class="mxch-insight-item">
                                    <span class="mxch-insight-label"><?php esc_html_e('Peak Activity', 'mxchat'); ?></span>
                                    <span class="mxch-insight-value">
                                        <?php
                                        if ($busiest_hour) {
                                            $hour = $busiest_hour->hour;
                                            $formatted_hour = date('g A', strtotime("$hour:00"));
                                            echo esc_html($formatted_hour);
                                        } else {
                                            echo esc_html__('N/A', 'mxchat');
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="mxch-insight-item">
                                    <span class="mxch-insight-label"><?php esc_html_e('Engagement Rate', 'mxchat'); ?></span>
                                    <span class="mxch-insight-value">
                                        <?php
                                        $engagement_rate = $total_chats > 0 ? round(($total_messages / $total_chats), 1) : 0;
                                        echo esc_html($engagement_rate . ' msg/chat');
                                        ?>
                                    </span>
                                </div>
                                <div class="mxch-insight-item">
                                    <span class="mxch-insight-label"><?php esc_html_e('Status', 'mxchat'); ?></span>
                                    <span class="mxch-insight-value">
                                        <?php
                                        if ($today_chats > 0) {
                                            echo '<span class="mxch-status-badge mxch-status-active">' . esc_html__('Active', 'mxchat') . '</span>';
                                        } else if ($week_chats > 0) {
                                            echo '<span class="mxch-status-badge mxch-status-moderate">' . esc_html__('Moderate', 'mxchat') . '</span>';
                                        } else {
                                            echo '<span class="mxch-status-badge mxch-status-quiet">' . esc_html__('Quiet', 'mxchat') . '</span>';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="mxch-insight-item">
                                    <span class="mxch-insight-label"><?php esc_html_e('Agent Tests', 'mxchat'); ?></span>
                                    <span class="mxch-insight-value"><?php echo esc_html($agent_tests); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity Chart -->
                <div class="mxch-card mxch-activity-chart-card">
                    <div class="mxch-card-header">
                        <h3 class="mxch-card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                            <?php esc_html_e('7-Day Activity', 'mxchat'); ?>
                        </h3>
                        <div class="mxch-chart-legend">
                            <span class="mxch-legend-item mxch-legend-chats">
                                <span class="mxch-legend-dot"></span>
                                <?php esc_html_e('Chats', 'mxchat'); ?>
                            </span>
                            <span class="mxch-legend-item mxch-legend-messages">
                                <span class="mxch-legend-dot"></span>
                                <?php esc_html_e('Messages', 'mxchat'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="mxch-card-body">
                        <div class="mxch-chart-container">
                            <canvas id="mxchat-activity-chart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="mxch-card">
                    <div class="mxch-card-header">
                        <h3 class="mxch-card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            <?php esc_html_e('Quick Actions', 'mxchat'); ?>
                        </h3>
                    </div>
                    <div class="mxch-card-body">
                        <div class="mxch-quick-actions">
                            <button type="button" class="mxch-quick-action-btn" data-action="view-chats">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                <span><?php esc_html_e('View All Chats', 'mxchat'); ?></span>
                            </button>
                            <button type="button" id="mxch-export-btn" class="mxch-quick-action-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                <span><?php esc_html_e('Export All Chats', 'mxchat'); ?></span>
                            </button>
                            <button type="button" class="mxch-quick-action-btn" data-action="settings">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                                <span><?php esc_html_e('Notification Settings', 'mxchat'); ?></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- All Chats Section - Split Panel Layout -->
            <div id="all-chats" class="mxch-section">
                <div class="mxch-split-panel">
                    <!-- Left Panel - Chat List -->
                    <div class="mxch-chat-list-panel">
                        <!-- Bulk Actions Toolbar -->
                        <div class="mxch-bulk-toolbar">
                            <label class="mxch-bulk-select-all">
                                <input type="checkbox" id="mxch-select-all">
                                <span><?php esc_html_e('All', 'mxchat'); ?></span>
                            </label>
                            <span class="mxch-selected-count" id="mxch-selected-count">0</span>
                            <div class="mxch-bulk-actions">
                                <button type="button" class="mxch-bulk-btn" id="mxch-sort-btn" title="<?php esc_attr_e('Sort', 'mxchat'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="16" y2="6"/><line x1="4" y1="12" x2="12" y2="12"/><line x1="4" y1="18" x2="8" y2="18"/><polyline points="15 15 18 18 21 15"/></svg>
                                    <span><?php esc_html_e('Sort', 'mxchat'); ?></span>
                                </button>
                                <button type="button" class="mxch-bulk-btn mxch-bulk-delete" id="mxch-delete-selected" disabled title="<?php esc_attr_e('Delete Selected', 'mxchat'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="mxch-panel-header">
                            <div class="mxch-search-wrapper">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="text" id="mxch-search-transcripts" class="mxch-search-input" placeholder="<?php esc_attr_e('Search chats...', 'mxchat'); ?>">
                            </div>
                            <div class="mxch-panel-title-row">
                                <span class="mxch-panel-count" id="mxch-chat-count">0 / 0 chats</span>
                                <div class="mxch-panel-actions">
                                    <button type="button" id="mxch-refresh-list" class="mxch-icon-btn" title="<?php esc_attr_e('Refresh', 'mxchat'); ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="mxch-chat-list" id="mxch-chat-list">
                            <!-- Chat list items loaded via AJAX -->
                        </div>
                        <div class="mxch-panel-footer">
                            <div class="mxch-pagination-simple" id="mxch-pagination">
                                <!-- Pagination controls -->
                            </div>
                        </div>
                    </div>

                    <!-- Right Panel - Conversation View -->
                    <div class="mxch-conversation-panel" id="mxch-conversation-panel">
                        <!-- Empty State -->
                        <div class="mxch-conversation-empty" id="mxch-conversation-empty">
                            <div class="mxch-empty-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            </div>
                            <h3><?php esc_html_e('Select a conversation', 'mxchat'); ?></h3>
                            <p><?php esc_html_e('Choose a chat from the list to view the full conversation.', 'mxchat'); ?></p>
                        </div>

                        <!-- Conversation Content (hidden initially) -->
                        <div class="mxch-conversation-content" id="mxch-conversation-content" style="display: none;">
                            <!-- Header -->
                            <div class="mxch-conversation-header">
                                <button type="button" class="mxch-mobile-back-btn" id="mxch-transcript-back-btn" style="display: none;" title="<?php esc_attr_e('Back to list', 'mxchat'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                                </button>
                                <div class="mxch-user-avatar" id="mxch-user-avatar">
                                    <span>MX</span>
                                </div>
                                <div class="mxch-user-info">
                                    <div class="mxch-user-name" id="mxch-user-name">User Name</div>
                                    <div class="mxch-user-meta" id="mxch-user-meta">User ID: 123</div>
                                </div>
                                <button type="button" class="mxch-details-toggle" id="mxch-toggle-details" title="<?php esc_attr_e('Show details', 'mxchat'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                </button>
                            </div>

                            <!-- Details Drawer (collapsible) -->
                            <div class="mxch-details-drawer" id="mxch-details-drawer" style="display: none;">
                                <div class="mxch-details-section">
                                    <h4><?php esc_html_e('Chat Details', 'mxchat'); ?></h4>
                                    <div class="mxch-detail-row">
                                        <span class="mxch-detail-label"><?php esc_html_e('Total Messages', 'mxchat'); ?></span>
                                        <span class="mxch-detail-value" id="mxch-detail-messages">0</span>
                                    </div>
                                    <div class="mxch-detail-row">
                                        <span class="mxch-detail-label"><?php esc_html_e('Started', 'mxchat'); ?></span>
                                        <span class="mxch-detail-value" id="mxch-detail-started">-</span>
                                    </div>
                                    <div class="mxch-detail-row">
                                        <span class="mxch-detail-label"><?php esc_html_e('Page', 'mxchat'); ?></span>
                                        <span class="mxch-detail-value" id="mxch-detail-page">-</span>
                                    </div>
                                    <div class="mxch-detail-row" id="mxch-detail-ip-row" style="display: none;">
                                        <span class="mxch-detail-label"><?php esc_html_e('IP Address', 'mxchat'); ?></span>
                                        <span class="mxch-detail-value" id="mxch-detail-ip">-</span>
                                    </div>
                                    <div class="mxch-detail-row" id="mxch-detail-email-row" style="display: none;">
                                        <span class="mxch-detail-label"><?php esc_html_e('Email', 'mxchat'); ?></span>
                                        <span class="mxch-detail-value" id="mxch-detail-email">-</span>
                                    </div>
                                </div>
                                <div class="mxch-details-section" id="mxch-clicked-section" style="display: none;">
                                    <h4><?php esc_html_e('Clicked Links', 'mxchat'); ?></h4>
                                    <div class="mxch-clicked-links" id="mxch-clicked-links">
                                        <!-- Links populated via JS -->
                                    </div>
                                </div>
                                <div class="mxch-details-actions">
                                    <button type="button" id="mxch-delete-current" class="mxch-btn mxch-btn-danger mxch-btn-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        <?php esc_html_e('Delete Chat', 'mxchat'); ?>
                                    </button>
                                    <button type="button" id="mxch-export-current" class="mxch-btn mxch-btn-secondary mxch-btn-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                        <?php esc_html_e('Export', 'mxchat'); ?>
                                    </button>
                                </div>
                                <div class="mxch-translate-section">
                                    <div class="mxch-translate-controls">
                                        <select id="mxch-translate-lang" class="mxch-translate-select" title="<?php esc_attr_e('Target language', 'mxchat'); ?>">
                                            <option value="en">English</option>
                                            <option value="es">Español</option>
                                            <option value="fr">Français</option>
                                            <option value="de">Deutsch</option>
                                            <option value="it">Italiano</option>
                                            <option value="pt">Português</option>
                                            <option value="nl">Nederlands</option>
                                            <option value="ru">Русский</option>
                                            <option value="zh">中文</option>
                                            <option value="ja">日本語</option>
                                            <option value="ko">한국어</option>
                                            <option value="ar">العربية</option>
                                            <option value="hi">हिन्दी</option>
                                            <option value="tr">Türkçe</option>
                                            <option value="pl">Polski</option>
                                            <option value="vi">Tiếng Việt</option>
                                            <option value="th">ไทย</option>
                                            <option value="id">Bahasa Indonesia</option>
                                            <option value="sv">Svenska</option>
                                            <option value="da">Dansk</option>
                                        </select>
                                        <button type="button" id="mxch-translate-btn" class="mxch-btn mxch-btn-primary mxch-btn-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 8 6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/></svg>
                                            <span class="mxch-translate-text"><?php esc_html_e('Translate', 'mxchat'); ?></span>
                                        </button>
                                        <button type="button" id="mxch-show-original-btn" class="mxch-btn mxch-btn-secondary mxch-btn-sm" style="display: none;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                                            <?php esc_html_e('Original', 'mxchat'); ?>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Messages Area -->
                            <div class="mxch-messages-area" id="mxch-messages-area">
                                <!-- Messages loaded via AJAX -->
                            </div>
                        </div>
                    </div>
                </div>

                <?php wp_nonce_field('mxchat_delete_chat_history', 'mxchat_delete_chat_nonce'); ?>
            </div>

            <!-- Notifications Section -->
            <div id="notifications" class="mxch-section">
                <div class="mxch-content-header">
                    <h1 class="mxch-content-title"><?php esc_html_e('Notification Settings', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Configure email notifications and transcript management options.', 'mxchat'); ?></p>
                </div>

                <div id="mxch-notifications-form" class="mxchat-autosave-section">

                    <!-- Email Notifications Card -->
                    <div class="mxch-card">
                        <div class="mxch-card-header">
                            <h3 class="mxch-card-title">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                <?php esc_html_e('Email Notifications', 'mxchat'); ?>
                            </h3>
                        </div>
                        <div class="mxch-card-body">
                            <?php
                            $options = get_option('mxchat_transcripts_options', array());
                            $enabled = isset($options['mxchat_enable_notifications']) ? $options['mxchat_enable_notifications'] : 0;
                            $email = isset($options['mxchat_notification_email']) ? $options['mxchat_notification_email'] : get_option('admin_email');
                            ?>
                            <div class="mxch-field">
                                <div class="mxch-toggle-row">
                                    <label class="mxchat-toggle-switch">
                                        <input type="checkbox"
                                               name="mxchat_transcripts_options[mxchat_enable_notifications]"
                                               class="mxchat-autosave-field"
                                               value="1"
                                               data-nonce="<?php echo wp_create_nonce('mxchat_autosave_nonce'); ?>"
                                               <?php checked(1, $enabled); ?>>
                                        <span class="mxchat-toggle-slider"></span>
                                    </label>
                                    <span class="mxch-toggle-label"><?php esc_html_e('Enable Chat Notifications', 'mxchat'); ?></span>
                                </div>
                                <p class="mxch-field-description"><?php esc_html_e('Send email notification when a new chat session starts.', 'mxchat'); ?></p>
                            </div>

                            <div class="mxch-field">
                                <label class="mxch-field-label"><?php esc_html_e('Notification Email', 'mxchat'); ?></label>
                                <input type="email"
                                       name="mxchat_transcripts_options[mxchat_notification_email]"
                                       class="mxch-input mxchat-autosave-field"
                                       value="<?php echo esc_attr($email); ?>"
                                       data-nonce="<?php echo wp_create_nonce('mxchat_autosave_nonce'); ?>">
                                <p class="mxch-field-description"><?php esc_html_e('Email address where notifications will be sent.', 'mxchat'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Auto-Email Transcript Card -->
                    <div class="mxch-card">
                        <div class="mxch-card-header">
                            <h3 class="mxch-card-title">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
                                <?php esc_html_e('Auto-Email Transcript', 'mxchat'); ?>
                            </h3>
                        </div>
                        <div class="mxch-card-body">
                            <?php
                            $auto_email_enabled = isset($options['mxchat_auto_email_transcript_enabled']) ? $options['mxchat_auto_email_transcript_enabled'] : 0;
                            $delay = isset($options['mxchat_auto_email_transcript_delay']) ? $options['mxchat_auto_email_transcript_delay'] : '30';
                            $require_contact = isset($options['mxchat_auto_email_transcript_require_contact']) ? $options['mxchat_auto_email_transcript_require_contact'] : 0;
                            ?>
                            <div class="mxch-field">
                                <div class="mxch-toggle-row">
                                    <label class="mxchat-toggle-switch">
                                        <input type="checkbox"
                                               name="mxchat_transcripts_options[mxchat_auto_email_transcript_enabled]"
                                               class="mxchat-autosave-field"
                                               value="1"
                                               data-nonce="<?php echo wp_create_nonce('mxchat_autosave_nonce'); ?>"
                                               <?php checked(1, $auto_email_enabled); ?>>
                                        <span class="mxchat-toggle-slider"></span>
                                    </label>
                                    <span class="mxch-toggle-label"><?php esc_html_e('Auto-Email Full Transcript', 'mxchat'); ?></span>
                                </div>
                                <p class="mxch-field-description"><?php esc_html_e('Automatically send the full conversation transcript after the chat ends.', 'mxchat'); ?></p>
                            </div>

                            <div class="mxch-field">
                                <label class="mxch-field-label"><?php esc_html_e('Send Transcript After', 'mxchat'); ?></label>
                                <select name="mxchat_transcripts_options[mxchat_auto_email_transcript_delay]"
                                        class="mxch-select mxchat-autosave-field"
                                        data-nonce="<?php echo wp_create_nonce('mxchat_autosave_nonce'); ?>">
                                    <option value="15" <?php selected($delay, '15'); ?>><?php esc_html_e('15 minutes', 'mxchat'); ?></option>
                                    <option value="30" <?php selected($delay, '30'); ?>><?php esc_html_e('30 minutes', 'mxchat'); ?></option>
                                    <option value="60" <?php selected($delay, '60'); ?>><?php esc_html_e('1 hour', 'mxchat'); ?></option>
                                </select>
                            </div>

                            <div class="mxch-field">
                                <div class="mxch-toggle-row">
                                    <label class="mxchat-toggle-switch">
                                        <input type="checkbox"
                                               name="mxchat_transcripts_options[mxchat_auto_email_transcript_require_contact]"
                                               class="mxchat-autosave-field"
                                               value="1"
                                               data-nonce="<?php echo wp_create_nonce('mxchat_autosave_nonce'); ?>"
                                               <?php checked(1, $require_contact); ?>>
                                        <span class="mxchat-toggle-slider"></span>
                                    </label>
                                    <span class="mxch-toggle-label"><?php esc_html_e('Only send if user provided contact info', 'mxchat'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transcript Management Card -->
                    <div class="mxch-card">
                        <div class="mxch-card-header">
                            <h3 class="mxch-card-title">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                <?php esc_html_e('Transcript Management', 'mxchat'); ?>
                            </h3>
                        </div>
                        <div class="mxch-card-body">
                            <?php
                            $auto_delete = isset($options['mxchat_auto_delete_transcripts']) ? $options['mxchat_auto_delete_transcripts'] : 'never';
                            ?>
                            <div class="mxch-field">
                                <label class="mxch-field-label"><?php esc_html_e('Auto-Delete Old Transcripts', 'mxchat'); ?></label>
                                <select name="mxchat_transcripts_options[mxchat_auto_delete_transcripts]"
                                        class="mxch-select mxchat-autosave-field"
                                        data-nonce="<?php echo wp_create_nonce('mxchat_autosave_nonce'); ?>">
                                    <option value="never" <?php selected($auto_delete, 'never'); ?>><?php esc_html_e('Never (Keep All)', 'mxchat'); ?></option>
                                    <option value="1week" <?php selected($auto_delete, '1week'); ?>><?php esc_html_e('After 1 Week', 'mxchat'); ?></option>
                                    <option value="2weeks" <?php selected($auto_delete, '2weeks'); ?>><?php esc_html_e('After 2 Weeks', 'mxchat'); ?></option>
                                    <option value="1month" <?php selected($auto_delete, '1month'); ?>><?php esc_html_e('After 1 Month', 'mxchat'); ?></option>
                                </select>
                                <p class="mxch-field-description"><?php esc_html_e('Automatically delete old chat transcripts to manage database size and privacy.', 'mxchat'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- RAG Context Modal -->
    <div id="mxch-rag-modal" class="mxch-modal-overlay" style="display: none;">
        <div class="mxch-modal-content">
            <div class="mxch-modal-header">
                <h2><?php esc_html_e('Message Context', 'mxchat'); ?></h2>
                <button type="button" class="mxch-modal-close">&times;</button>
            </div>
            <div class="mxch-modal-body">
                <!-- Tabs -->
                <div class="mxch-context-tabs">
                    <button type="button" class="mxch-context-tab active" data-tab="sources">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        <?php esc_html_e('Sources', 'mxchat'); ?>
                        <span class="mxch-tab-badge" id="mxch-sources-count" style="display: none;">0</span>
                    </button>
                    <button type="button" class="mxch-context-tab" data-tab="actions">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        <?php esc_html_e('Actions', 'mxchat'); ?>
                        <span class="mxch-tab-badge" id="mxch-actions-count" style="display: none;">0</span>
                    </button>
                </div>

                <div class="mxch-rag-loading" style="display: none;">
                    <span class="spinner is-active"></span>
                    <?php esc_html_e('Loading context...', 'mxchat'); ?>
                </div>

                <!-- Sources Tab Content -->
                <div class="mxch-tab-content" id="mxch-tab-sources">
                    <div class="mxch-rag-content">
                        <!-- RAG context will be populated via JavaScript -->
                    </div>
                </div>

                <!-- Actions Tab Content -->
                <div class="mxch-tab-content" id="mxch-tab-actions" style="display: none;">
                    <div class="mxch-actions-content">
                        <!-- Action scores will be populated via JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
