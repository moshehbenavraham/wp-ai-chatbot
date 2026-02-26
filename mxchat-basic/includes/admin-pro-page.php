<?php
/**
 * MxChat Pro & Extensions Page - Consolidated Sidebar Navigation
 *
 * @package MxChat
 * @since 2.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the consolidated Pro & Extensions page
 */
function mxchat_render_pro_page($admin_instance, $addons_config) {
    $is_activated = $admin_instance->is_activated();
    $plugin_url = plugin_dir_url(dirname(__FILE__));

    // License data
    $license_status = get_option('mxchat_license_status', 'inactive');
    $license_email = get_option('mxchat_pro_email', '');
    $license_key = get_option('mxchat_activation_key', '');
    $current_domain = parse_url(home_url(), PHP_URL_HOST);
    $is_domain_linked = $admin_instance->is_current_activation_linked($current_domain);
    $license_management_url = 'https://mxchat.ai/my-account/orders/';

    // Mask the license key for display
    $masked_key = '';
    if (!empty($license_key)) {
        $masked_key = str_repeat('•', max(0, strlen($license_key) - 4)) . substr($license_key, -4);
    }

    // Count addon statuses
    $active_count = 0;
    $installed_count = 0;
    foreach ($addons_config as $slug => $addon) {
        $status = mxchat_get_addon_status_info($addon['plugin_file'], $addon['config_page']);
        if ($status['status'] === 'active') {
            $active_count++;
            $installed_count++;
        } elseif ($status['status'] === 'inactive') {
            $installed_count++;
        }
    }
    ?>
    <div class="mxch-admin-wrapper mxch-pro-wrapper">
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
                <span class="mxch-mobile-menu-title"><?php esc_html_e('Pro & Extensions', 'mxchat'); ?></span>
                <button type="button" class="mxch-mobile-menu-close" aria-label="<?php esc_attr_e('Close menu', 'mxchat'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <nav class="mxch-mobile-menu-nav">
                <!-- License Status (Mobile) -->
                <div class="mxch-mobile-nav-section">
                    <div class="mxch-mobile-nav-section-title"><?php esc_html_e('License', 'mxchat'); ?></div>
                    <div class="mxch-mobile-license-status">
                        <span class="mxch-license-badge <?php echo $license_status === 'active' ? 'active' : 'inactive'; ?>">
                            <?php echo $license_status === 'active' ? esc_html__('Active', 'mxchat') : esc_html__('Inactive', 'mxchat'); ?>
                        </span>
                    </div>
                </div>

                <!-- Extensions Section (Mobile) -->
                <div class="mxch-mobile-nav-section">
                    <div class="mxch-mobile-nav-section-title"><?php esc_html_e('Extensions', 'mxchat'); ?></div>
                    <button class="mxch-mobile-nav-link active" data-target="overview">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                        <span><?php esc_html_e('Overview', 'mxchat'); ?></span>
                    </button>
                    <?php foreach ($addons_config as $slug => $addon):
                        $status = mxchat_get_addon_status_info($addon['plugin_file'], $addon['config_page']);
                    ?>
                    <button class="mxch-mobile-nav-link mxch-addon-nav-link" data-target="addon-<?php echo esc_attr($slug); ?>">
                        <span class="mxch-addon-status-dot <?php echo esc_attr($status['status']); ?>"></span>
                        <span><?php echo esc_html(!empty($addon['sidebar_title']) ? $addon['sidebar_title'] : str_replace('MxChat ', '', $addon['title'])); ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </nav>
        </div>

        <!-- Sidebar Navigation -->
        <aside class="mxch-sidebar mxch-pro-sidebar">
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
                <!-- License Status - Simple Badge -->
                <div class="mxch-nav-section mxch-license-section">
                    <div class="mxch-nav-section-title"><?php esc_html_e('License', 'mxchat'); ?></div>
                    <div class="mxch-sidebar-license-status">
                        <span class="mxch-license-badge <?php echo $license_status === 'active' ? 'active' : 'inactive'; ?>">
                            <?php echo $license_status === 'active' ? esc_html__('Active', 'mxchat') : esc_html__('Inactive', 'mxchat'); ?>
                        </span>
                    </div>
                </div>

                <!-- Extensions Section -->
                <div class="mxch-nav-section mxch-extensions-section">
                    <div class="mxch-nav-section-title">
                        <?php esc_html_e('Extensions', 'mxchat'); ?>
                        <span class="mxch-nav-section-count"><?php echo esc_html(count($addons_config)); ?></span>
                    </div>

                    <div class="mxch-nav-item" data-section="overview">
                        <button class="mxch-nav-link active" data-target="overview">
                            <span class="mxch-nav-link-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                            </span>
                            <span class="mxch-nav-link-text"><?php esc_html_e('Overview', 'mxchat'); ?></span>
                        </button>
                    </div>

                    <?php foreach ($addons_config as $slug => $addon):
                        $status = mxchat_get_addon_status_info($addon['plugin_file'], $addon['config_page']);
                    ?>
                    <div class="mxch-nav-item mxch-addon-item" data-section="addon-<?php echo esc_attr($slug); ?>">
                        <button class="mxch-nav-link mxch-addon-nav-link" data-target="addon-<?php echo esc_attr($slug); ?>">
                            <span class="mxch-addon-status-dot <?php echo esc_attr($status['status']); ?>"></span>
                            <span class="mxch-nav-link-text"><?php echo esc_html(!empty($addon['sidebar_title']) ? $addon['sidebar_title'] : str_replace('MxChat ', '', $addon['title'])); ?></span>
                        </button>
                    </div>
                    <?php endforeach; ?>
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
        <main class="mxch-content mxch-pro-content">
            <?php if (!$is_activated): ?>
            <div class="mxch-pro-banner">
                <div class="mxch-pro-banner-content">
                    <h3 class="mxch-pro-banner-title"><?php esc_html_e('Unlock All Premium Features', 'mxchat'); ?></h3>
                    <p class="mxch-pro-banner-text"><?php esc_html_e('Get MxChat Pro for unlimited access to powerful add-ons including Admin AI Assistant, Forms Builder, AI Theme Generator, and more!', 'mxchat'); ?></p>
                </div>
                <a href="https://mxchat.ai/" target="_blank" class="mxch-pro-banner-btn"><span><?php esc_html_e('Get Pro', 'mxchat'); ?></span></a>
            </div>
            <?php endif; ?>

            <!-- Overview Section -->
            <div id="overview" class="mxch-section active">
                <div class="mxch-content-header">
                    <h1 class="mxch-content-title"><?php esc_html_e('Pro & Extensions', 'mxchat'); ?></h1>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Manage your license and extend MxChat with powerful add-ons.', 'mxchat'); ?></p>
                </div>

                <!-- License Management Card -->
                <div class="mxch-card mxch-license-mgmt-card">
                    <div class="mxch-card-header">
                        <h3 class="mxch-card-title">
                            <svg class="mxch-card-title-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                            <?php esc_html_e('License Management', 'mxchat'); ?>
                        </h3>
                        <span class="mxch-license-main-badge <?php echo $license_status === 'active' ? 'active' : 'inactive'; ?>">
                            <?php echo $license_status === 'active' ? esc_html__('Active', 'mxchat') : esc_html__('Inactive', 'mxchat'); ?>
                        </span>
                    </div>
                    <div class="mxch-card-body">
                        <?php if ($license_status === 'active'): ?>
                            <!-- Active License Display -->
                            <div class="mxch-license-info-grid">
                                <div class="mxch-license-info-item">
                                    <span class="mxch-license-info-label"><?php esc_html_e('Domain', 'mxchat'); ?></span>
                                    <span class="mxch-license-info-value">
                                        <?php echo esc_html($current_domain); ?>
                                        <?php if ($is_domain_linked): ?>
                                            <span class="mxch-domain-linked"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> <?php esc_html_e('Linked', 'mxchat'); ?></span>
                                        <?php else: ?>
                                            <button type="button" id="link-domain-button" class="mxch-link-domain-btn"><?php esc_html_e('Link Domain', 'mxchat'); ?></button>
                                            <span id="domain-link-status"></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="mxch-license-info-item">
                                    <span class="mxch-license-info-label"><?php esc_html_e('Email', 'mxchat'); ?></span>
                                    <span class="mxch-license-info-value"><?php echo esc_html($license_email); ?></span>
                                </div>
                                <div class="mxch-license-info-item">
                                    <span class="mxch-license-info-label"><?php esc_html_e('License Key', 'mxchat'); ?></span>
                                    <span class="mxch-license-info-value">
                                        <code class="mxch-license-key-code"><?php echo esc_html($license_key); ?></code>
                                    </span>
                                </div>
                            </div>

                            <div class="mxch-license-footer-actions">
                                <a href="<?php echo esc_url($license_management_url); ?>" target="_blank" class="mxch-btn mxch-btn-secondary">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                    <?php esc_html_e('Manage Account', 'mxchat'); ?>
                                </a>
                                <button type="button" id="deactivate-license-button" class="mxch-btn mxch-btn-danger">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                    <?php esc_html_e('Deactivate License', 'mxchat'); ?>
                                </button>
                                <span id="deactivate-status"></span>
                            </div>
                        <?php else: ?>
                            <!-- Activation Form -->
                            <form id="mxchat-activation-form" class="mxch-activation-form">
                                <div class="mxch-form-row">
                                    <div class="mxch-field">
                                        <label class="mxch-field-label" for="mxchat_pro_email"><?php esc_html_e('Email Address', 'mxchat'); ?></label>
                                        <input type="email" id="mxchat_pro_email" name="mxchat_pro_email" value="<?php echo esc_attr(get_option('mxchat_pro_email')); ?>" class="mxch-input" required placeholder="your@email.com" />
                                    </div>
                                    <div class="mxch-field">
                                        <label class="mxch-field-label" for="mxchat_activation_key"><?php esc_html_e('Activation Key', 'mxchat'); ?></label>
                                        <input type="text" id="mxchat_activation_key" name="mxchat_activation_key" value="<?php echo esc_attr(get_option('mxchat_activation_key')); ?>" class="mxch-input" required placeholder="XXXX-XXXX-XXXX-XXXX" />
                                    </div>
                                </div>
                                <input type="hidden" id="mxchat_domain" name="domain" value="<?php echo esc_attr($current_domain); ?>" />
                                <!-- Hidden license status element required by activation-script.js -->
                                <span id="mxchat-license-status" class="<?php echo $license_status; ?>" style="display: none;"><?php echo $license_status === 'active' ? esc_html__('Active', 'mxchat') : esc_html__('Inactive', 'mxchat'); ?></span>
                                <div class="mxch-form-actions">
                                    <button type="submit" id="activate_license_button" class="mxch-btn mxch-btn-primary mxch-btn-lg">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                                        <?php esc_html_e('Activate License', 'mxchat'); ?>
                                    </button>
                                    <div id="mxchat-activation-spinner" class="mxch-spinner" style="display: none;"></div>
                                </div>
                                <p class="mxch-field-hint">
                                    <?php esc_html_e("Don't have a license?", 'mxchat'); ?>
                                    <a href="https://mxchat.ai/" target="_blank"><?php esc_html_e('Get MxChat Pro', 'mxchat'); ?></a>
                                </p>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Extensions Overview Grid -->
                <div class="mxch-content-header mxch-mt-xl">
                    <h2 class="mxch-content-title mxch-h2"><?php esc_html_e('Available Extensions', 'mxchat'); ?></h2>
                    <p class="mxch-content-subtitle"><?php esc_html_e('Click on any extension to learn more and get started.', 'mxchat'); ?></p>
                </div>

                <div class="mxch-extensions-grid">
                    <?php foreach ($addons_config as $slug => $addon):
                        $status = mxchat_get_addon_status_info($addon['plugin_file'], $addon['config_page']);
                    ?>
                    <div class="mxch-extension-card" data-addon="<?php echo esc_attr($slug); ?>" style="--card-accent: <?php echo esc_attr($addon['accent']); ?>">
                        <div class="mxch-extension-card-header">
                            <h3 class="mxch-extension-title"><?php echo esc_html($addon['title']); ?></h3>
                            <span class="mxch-extension-status <?php echo esc_attr($status['status']); ?>">
                                <?php echo esc_html(ucfirst(str_replace('-', ' ', $status['status']))); ?>
                            </span>
                        </div>
                        <p class="mxch-extension-desc"><?php echo esc_html($addon['description']); ?></p>
                        <div class="mxch-extension-card-footer">
                            <?php if ($status['status'] === 'active'): ?>
                                <a href="<?php echo esc_url($status['action_url']); ?>" class="mxch-btn mxch-btn-primary mxch-btn-sm">
                                    <?php esc_html_e('Configure', 'mxchat'); ?>
                                </a>
                            <?php elseif ($status['status'] === 'inactive'): ?>
                                <a href="<?php echo esc_url($status['action_url']); ?>" class="mxch-btn mxch-btn-secondary mxch-btn-sm">
                                    <?php esc_html_e('Activate', 'mxchat'); ?>
                                </a>
                            <?php else: ?>
                                <a href="<?php echo esc_url($addon['url']); ?>" target="_blank" class="mxch-btn mxch-btn-secondary mxch-btn-sm">
                                    <?php esc_html_e('Get Extension', 'mxchat'); ?>
                                </a>
                            <?php endif; ?>
                            <button type="button" class="mxch-btn mxch-btn-ghost mxch-btn-sm mxch-view-addon-btn" data-target="addon-<?php echo esc_attr($slug); ?>">
                                <?php esc_html_e('Learn More', 'mxchat'); ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Individual Addon Detail Sections (Hero Style) -->
            <?php foreach ($addons_config as $slug => $addon):
                $status = mxchat_get_addon_status_info($addon['plugin_file'], $addon['config_page']);
                $screenshot_url = $plugin_url . 'images/addons/' . $slug . '.png';
            ?>
            <div id="addon-<?php echo esc_attr($slug); ?>" class="mxch-section mxch-addon-detail">
                <!-- Back Link -->
                <div class="mxch-addon-page-header">
                    <button type="button" class="mxch-back-link" data-target="overview">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                        <?php esc_html_e('Back to Extensions', 'mxchat'); ?>
                    </button>
                </div>

                <!-- Hero Section -->
                <div class="mxch-addon-hero-section">
                    <!-- Left: Content -->
                    <div class="mxch-addon-hero-content">
                        <h1 class="mxch-addon-hero-title"><?php echo esc_html($addon['title']); ?></h1>
                        <p class="mxch-addon-hero-desc"><?php echo esc_html($addon['description']); ?></p>

                        <!-- Feature highlights -->
                        <?php if (!empty($addon['key_benefits'])): ?>
                        <div class="mxch-addon-hero-features">
                            <?php foreach ($addon['key_benefits'] as $benefit): ?>
                            <div class="mxch-addon-hero-feature">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                <span><?php echo esc_html($benefit); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Action Buttons - Context aware -->
                        <div class="mxch-addon-hero-actions">
                            <?php if ($status['status'] === 'active'): ?>
                                <!-- Already active - Configure button -->
                                <a href="<?php echo esc_url($status['action_url']); ?>" class="mxch-btn mxch-btn-primary mxch-btn-lg">
                                    <?php esc_html_e('Configure Extension', 'mxchat'); ?>
                                </a>
                            <?php elseif ($status['status'] === 'inactive'): ?>
                                <!-- Installed but inactive - Activate button -->
                                <a href="<?php echo esc_url($status['action_url']); ?>" class="mxch-btn mxch-btn-primary mxch-btn-lg">
                                    <?php esc_html_e('Activate Extension', 'mxchat'); ?>
                                </a>
                            <?php elseif ($is_activated): ?>
                                <!-- Has Pro license but extension not installed - Download -->
                                <?php $download_link = !empty($addon['download_url']) ? $addon['download_url'] : $addon['url']; ?>
                                <a href="<?php echo esc_url($download_link); ?>" target="_blank" class="mxch-btn mxch-btn-primary mxch-btn-lg">
                                    <?php esc_html_e('Download Add-on', 'mxchat'); ?>
                                </a>
                            <?php else: ?>
                                <!-- No Pro license - Purchase -->
                                <a href="https://mxchat.ai/" target="_blank" class="mxch-btn mxch-btn-primary mxch-btn-lg">
                                    <?php esc_html_e('Purchase MxChat', 'mxchat'); ?>
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo esc_url($addon['url']); ?>" target="_blank" class="mxch-btn mxch-btn-outline mxch-btn-lg">
                                <?php esc_html_e('Documentation', 'mxchat'); ?>
                            </a>
                        </div>
                    </div>

                    <!-- Right: Screenshot or Feature Visual -->
                    <div class="mxch-addon-hero-image">
                        <?php if (!empty($addon['hero_features'])): ?>
                            <!-- Styled feature showcase (no screenshot) -->
                            <div class="mxch-addon-feature-showcase" style="--showcase-accent: <?php echo esc_attr($addon['accent']); ?>">
                                <?php foreach ($addon['hero_features'] as $hf): ?>
                                <div class="mxch-addon-showcase-item">
                                    <div class="mxch-addon-showcase-icon"><?php echo $hf['icon']; ?></div>
                                    <div class="mxch-addon-showcase-text">
                                        <strong><?php echo esc_html($hf['title']); ?></strong>
                                        <span><?php echo esc_html($hf['desc']); ?></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <img src="<?php echo esc_url($screenshot_url); ?>" alt="<?php echo esc_attr($addon['title']); ?>" onerror="this.parentElement.innerHTML='<div class=\'mxch-addon-image-placeholder\'><svg xmlns=\'http://www.w3.org/2000/svg\' width=\'48\' height=\'48\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.5\'><rect x=\'3\' y=\'3\' width=\'18\' height=\'18\' rx=\'2\' ry=\'2\'/><circle cx=\'8.5\' cy=\'8.5\' r=\'1.5\'/><polyline points=\'21 15 16 10 5 21\'/></svg></div>'">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </main>
    </div>

    <!-- Hidden fields for JavaScript -->
    <input type="hidden" id="mxchat-ajax-url" value="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" />
    <input type="hidden" id="mxchat-nonce" value="<?php echo esc_attr(wp_create_nonce('mxchat_activate_license_nonce')); ?>" />
    <input type="hidden" id="mxchat-api-url" value="https://mxchat.ai" />
    <?php
}

/**
 * Get addon status information
 */
function mxchat_get_addon_status_info($plugin_file, $config_page) {
    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $all_plugins = get_plugins();
    $active_plugins = get_option('active_plugins', array());

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
