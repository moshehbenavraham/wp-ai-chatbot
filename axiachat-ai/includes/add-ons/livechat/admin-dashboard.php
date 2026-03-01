<?php
/**
 * Live Chat Admin Dashboard
 *
 * Provides a real-time interface for agents to handle chat sessions.
 *
 * @package AIChat
 * @subpackage LiveChat
 * @since 2.5.0
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Register admin menu
 */
add_action('admin_menu', 'aichat_livechat_admin_menu', 25);

function aichat_livechat_admin_menu()
{
	$pending_count = 0;
	if (class_exists('AIChat_LiveChat_Manager')) {
		$pending_count = AIChat_LiveChat_Manager::count_pending();
	}
	$badge = $pending_count > 0 ? ' <span class="awaiting-mod">' . $pending_count . '</span>' : '';

	add_submenu_page(
		'aichat-settings',
		__('Live Chat', 'axiachat-ai'),
		__('Live Chat', 'axiachat-ai') . $badge,
		'manage_options',
		'aichat-livechat',
		'aichat_livechat_render_dashboard'
	);
}

/**
 * Enqueue admin assets
 */
add_action('admin_enqueue_scripts', 'aichat_livechat_admin_assets');

function aichat_livechat_admin_assets($hook)
{
	if (strpos($hook, 'aichat-livechat') === false) {
		return;
	}

	// Bootstrap CSS
	wp_enqueue_style(
		'bootstrap',
		AICHAT_PLUGIN_URL . 'assets/vendor/bootstrap/css/bootstrap.min.css',
		[],
		'5.3.0'
	);

	// Bootstrap JS
	wp_enqueue_script(
		'bootstrap',
		AICHAT_PLUGIN_URL . 'assets/vendor/bootstrap/js/bootstrap.bundle.min.js',
		[],
		'5.3.0',
		true
	);

	wp_enqueue_style(
		'aichat-livechat-admin',
		AICHAT_LIVECHAT_URL . 'assets/livechat-admin.css',
		['bootstrap'],
		AICHAT_LIVECHAT_VERSION
	);

	wp_enqueue_script(
		'aichat-livechat-admin',
		AICHAT_LIVECHAT_URL . 'assets/livechat-admin.js',
		['jquery', 'bootstrap'],
		AICHAT_LIVECHAT_VERSION,
		true
	);

	wp_localize_script('aichat-livechat-admin', 'aichatLiveChat', [
		'ajaxUrl' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('aichat_livechat_admin_nonce'),
		'pollInterval' => 3000, // 3 seconds
		'notificationSound' => (bool) get_option('aichat_livechat_notification_sound', 1),
		'strings' => [
			'confirm_take' => 'Take this session?',
			'confirm_return' => 'Return this session to the bot?',
			'sending' => 'Sending...',
			'no_sessions' => 'No active sessions',
			'connected' => 'Connected',
			'pending' => 'Waiting',
			'active' => 'Active',
		],
	]);
}

/**
 * Render dashboard
 */
function aichat_livechat_render_dashboard()
{
	// Safety check - ensure manager class is loaded
	if (! class_exists('AIChat_LiveChat_Manager')) {
		echo '<div class="wrap"><h1>Live Chat</h1>';
		echo '<div class="notice notice-error"><p>Live Chat module not properly loaded. Please try deactivating and reactivating the plugin.</p></div>';
		echo '</div>';

		return;
	}

	// Ensure tables exist
	AIChat_LiveChat_Manager::create_tables();

	// Check which tab is active
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page state from query params.
	$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';

	$sessions = AIChat_LiveChat_Manager::get_active_sessions();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page state from query params.
	$current_session = isset($_GET['session']) ? sanitize_text_field(wp_unslash($_GET['session'])) : null;
	$settings = AIChat_LiveChat_Manager::get_settings();
	$pending_count = AIChat_LiveChat_Manager::count_pending();
	?>
    <div class="wrap aichat-livechat-wrap">
        <h1 class="wp-heading-inline mb-3">
            <span class="dashicons dashicons-format-chat" style="margin-right: 8px;"></span>
            Live Chat
        </h1>
        
        <!-- Bootstrap Tabs -->
        <ul class="nav nav-tabs mb-4" id="livechatTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>" 
                   href="<?php echo esc_url(admin_url('admin.php?page=aichat-livechat&tab=dashboard')); ?>">
                    <span class="dashicons dashicons-format-chat" style="font-size:16px; vertical-align:middle;"></span>
                    Live Chat
                    <?php if ($pending_count > 0) : ?>
                        <span class="badge bg-danger"><?php echo intval($pending_count); ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $active_tab === 'settings' ? 'active' : ''; ?>" 
                   href="<?php echo esc_url(admin_url('admin.php?page=aichat-livechat&tab=settings')); ?>">
                    <span class="dashicons dashicons-admin-settings" style="font-size:16px; vertical-align:middle;"></span>
                    Settings
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $active_tab === 'help' ? 'active' : ''; ?>" 
                   href="<?php echo esc_url(admin_url('admin.php?page=aichat-livechat&tab=help')); ?>">
                    <span class="dashicons dashicons-editor-help" style="font-size:16px; vertical-align:middle;"></span>
                    Help
                </a>
            </li>
        </ul>
        
        <?php if ($active_tab === 'settings') : ?>
            <?php aichat_livechat_render_settings_content(); ?>
        <?php elseif ($active_tab === 'help') : ?>
            <?php aichat_livechat_render_help_content(); ?>
        <?php else : ?>
            <?php aichat_livechat_render_dashboard_content($sessions, $current_session, $settings); ?>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render dashboard content (chat panel)
 */
function aichat_livechat_render_dashboard_content($sessions, $current_session, $settings)
{
	$is_available = (bool) get_option('aichat_livechat_available', 1);
	$agents_online = 1; // Simplified
	?>
        <div class="aichat-livechat-status-bar">
            <span class="status-indicator <?php echo $is_available ? 'status-available' : 'status-unavailable'; ?>">
                <span class="status-dot"></span>
                <?php echo $is_available ? 'Available' : 'Unavailable'; ?>
            </span>
            <span class="status-agents">
                <span class="dashicons dashicons-admin-users"></span>
                <?php echo intval($agents_online); ?> agent<?php echo $agents_online !== 1 ? 's' : ''; ?> online
            </span>
        </div>
        
        <div class="aichat-livechat-container">
            <!-- Sessions List -->
            <div class="aichat-livechat-sidebar">
                <div class="aichat-livechat-sidebar-header">
                    <h3>Active Sessions</h3>
                    <span id="aichat-livechat-count" class="aichat-badge"><?php echo count($sessions); ?></span>
                </div>
                
                <div id="aichat-livechat-sessions" class="aichat-sessions-list">
                    <?php if (empty($sessions)) : ?>
                        <div class="no-sessions">
                            <span class="dashicons dashicons-testimonial"></span>
                            <p>No active sessions</p>
                        </div>
                    <?php else :
                    	// Group sessions
                    	$pending = array_filter($sessions, fn ($s) => $s->status === 'pending_agent');
                    	$active = array_filter($sessions, fn ($s) => $s->status === 'agent_connected');
                    	?>
                        <?php if (! empty($pending)) : ?>
                        <div class="sessions-group">
                            <h4><span class="dashicons dashicons-clock"></span> <?php echo esc_html__('Waiting', 'axiachat-ai'); ?> (<?php echo count($pending); ?>)</h4>
                            <?php foreach ($pending as $session) : ?>
                                <?php aichat_livechat_render_session_item($session, $current_session); ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (! empty($active)) : ?>
                        <div class="sessions-group">
                            <h4><span class="dashicons dashicons-format-chat"></span> <?php echo esc_html__('Active', 'axiachat-ai'); ?> (<?php echo count($active); ?>)</h4>
                            <?php foreach ($active as $session) : ?>
                                <?php aichat_livechat_render_session_item($session, $current_session); ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
            </div>
            
            <!-- Chat Area -->
            <div class="aichat-livechat-main">
                <?php if ($current_session) : ?>
                    <?php aichat_livechat_render_chat_panel($current_session); ?>
                <?php else : ?>
                    <div class="aichat-livechat-placeholder">
                        <span class="dashicons dashicons-format-chat"></span>
                        <h2><?php echo esc_html__('Select a session to start', 'axiachat-ai'); ?></h2>
                        <p><?php echo esc_html__('Click on a session from the list to view the conversation and respond.', 'axiachat-ai'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php
}

/**
 * Render session item in sidebar
 */
function aichat_livechat_render_session_item($session, $current_session = null)
{
	$is_active = $current_session === $session->session_id;
	$status_class = $session->status === 'pending_agent' ? 'status-pending' : 'status-active';
	$priority_class = $session->priority >= 3 ? 'priority-urgent' : ($session->priority >= 2 ? 'priority-high' : '');
	$url = admin_url('admin.php?page=aichat-livechat&session=' . urlencode($session->session_id));
	?>
    <a href="<?php echo esc_url($url); ?>" 
       class="session-item <?php echo $is_active ? 'active' : ''; ?> <?php echo esc_attr($status_class); ?> <?php echo esc_attr($priority_class); ?>"
       data-session="<?php echo esc_attr($session->session_id); ?>">
        
        <div class="session-avatar">
            <span class="dashicons dashicons-admin-users"></span>
        </div>
        
        <div class="session-info">
            <div class="session-name">
                <?php echo esc_html($session->visitor_name ?: __('Visitor', 'axiachat-ai')); ?>
            </div>
            <div class="session-reason">
                <?php echo esc_html($session->handoff_reason ?: ''); ?>
            </div>
            <div class="session-meta">
                <?php echo esc_html(human_time_diff(strtotime($session->created_at), current_time('timestamp'))); ?> ago
            </div>
        </div>
        
        <?php if ($session->status === 'agent_connected' && $session->agent_name) : ?>
        <div class="session-agent">
            <span class="dashicons dashicons-businessman"></span>
            <?php echo esc_html($session->agent_name); ?>
        </div>
        <?php endif; ?>
    </a>
    <?php
}

/**
 * Render chat panel for a session
 */
function aichat_livechat_render_chat_panel($session_id)
{
	global $wpdb;

	$table = AIChat_LiveChat_Manager::table_sessions();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$session = $wpdb->get_row($wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
		"SELECT * FROM {$table} WHERE session_id = %s",
		$session_id
	));

	if (! $session) {
		echo '<div class="notice notice-error"><p>' . esc_html__('Session not found.', 'axiachat-ai') . '</p></div>';

		return;
	}

	$messages = AIChat_LiveChat_Manager::get_all_messages($session_id);
	$last_message_id = AIChat_LiveChat_Manager::get_last_message_id($session_id);
	$can_respond = $session->status === 'agent_connected' && (int) $session->agent_id === get_current_user_id();
	$can_take = $session->status === 'pending_agent';
	$can_view = true; // Always allow viewing messages before taking
	?>
    <div class="aichat-livechat-chat" data-session="<?php echo esc_attr($session_id); ?>" data-last-message-id="<?php echo esc_attr($last_message_id); ?>">
        <!-- Header -->
        <div class="aichat-livechat-chat-header">
            <div class="chat-header-info">
                <h3>
                    <?php echo esc_html($session->visitor_name ?: __('Visitor', 'axiachat-ai')); ?>
                    <?php if (! empty($session->visitor_email)) : ?>
                        <small>&lt;<?php echo esc_html($session->visitor_email); ?>&gt;</small>
                    <?php endif; ?>
                </h3>
                <div class="chat-header-meta">
                    <?php if (! empty($session->page_url)) : ?>
                        <span title="<?php echo esc_attr($session->page_url); ?>">
                            <span class="dashicons dashicons-admin-links"></span>
                            <?php echo esc_html(wp_parse_url($session->page_url, PHP_URL_PATH) ?: '/'); ?>
                        </span>
                    <?php endif; ?>
                    <span>
                        <span class="dashicons dashicons-clock"></span>
                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($session->created_at))); ?>
                    </span>
                </div>
            </div>
            
            <div class="chat-header-actions">
                <?php if ($can_take) : ?>
                    <button type="button" class="button button-primary" id="aichat-take-session" data-session="<?php echo esc_attr($session_id); ?>">
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php echo esc_html__('Take Session', 'axiachat-ai'); ?>
                    </button>
                <?php endif; ?>
                
                <?php if ($can_respond) : ?>
                    <button type="button" class="button" id="aichat-return-to-bot" data-session="<?php echo esc_attr($session_id); ?>">
                        <span class="dashicons dashicons-controls-repeat"></span>
                        Return to Bot
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Handoff reason banner -->
        <?php if ($session->handoff_reason) : ?>
            <div class="aichat-livechat-reason-banner">
                <strong><?php echo esc_html__('Handoff reason:', 'axiachat-ai'); ?></strong>
                <?php echo esc_html($session->handoff_reason); ?>
            </div>
        <?php endif; ?>
        
        <!-- Messages -->
        <div class="aichat-livechat-messages" id="aichat-livechat-messages">
            <?php foreach ($messages as $msg) : ?>
                <?php aichat_livechat_render_message($msg); ?>
            <?php endforeach; ?>
        </div>
        
        <!-- Reply form -->
        <?php if ($can_respond || $can_take) : ?>
            <div class="aichat-livechat-reply">
                <form id="aichat-livechat-reply-form" <?php echo $can_take ? 'style="display:none;"' : ''; ?>>
                    <input type="hidden" name="session_id" value="<?php echo esc_attr($session_id); ?>">
                    <textarea name="message" id="aichat-reply-message" placeholder="<?php echo esc_attr__('Type your message...', 'axiachat-ai'); ?>" rows="2"></textarea>
                    <div class="reply-actions">
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-arrow-right-alt"></span>
                            <?php echo esc_html__('Send', 'axiachat-ai'); ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render a single message
 */
function aichat_livechat_render_message($msg)
{
	$type_class = 'message-' . esc_attr($msg->sender_type);
	?>
    <div class="aichat-livechat-message <?php echo esc_attr($type_class); ?>">
        <div class="message-avatar">
            <?php if ($msg->sender_type === 'visitor') : ?>
                <span class="dashicons dashicons-admin-users"></span>
            <?php elseif ($msg->sender_type === 'bot') : ?>
                <span class="dashicons dashicons-format-status"></span>
            <?php else : ?>
                <span class="dashicons dashicons-businessperson"></span>
            <?php endif; ?>
        </div>
        <div class="message-content">
            <div class="message-header">
                <span class="message-sender">
                    <?php
					if ($msg->sender_type === 'visitor') {
						echo esc_html__('Visitor', 'axiachat-ai');
					} elseif ($msg->sender_type === 'bot') {
						echo esc_html__('Bot', 'axiachat-ai');
					} else {
						echo esc_html($msg->sender_name ?: __('Agent', 'axiachat-ai'));
					}
	?>
                </span>
                <span class="message-time">
                    <?php echo esc_html(date_i18n('H:i', strtotime($msg->created_at))); ?>
                </span>
            </div>
            <div class="message-text"><?php echo wp_kses_post(nl2br(esc_html($msg->message))); ?></div>
        </div>
    </div>
    <?php
}

/**
 * Add pending count to admin bar
 */
add_action('admin_bar_menu', 'aichat_livechat_admin_bar', 100);

function aichat_livechat_admin_bar($wp_admin_bar)
{
	if (! current_user_can('manage_options')) {
		return;
	}

	if (! get_option('aichat_addon_livechat_enabled', 0)) {
		return;
	}

	$pending = AIChat_LiveChat_Manager::count_pending();

	if ($pending > 0) {
		$wp_admin_bar->add_node([
			'id' => 'aichat-livechat',
			'title' => '<span class="ab-icon dashicons dashicons-format-chat"></span> <span class="aichat-livechat-badge">' . $pending . '</span>',
			'href' => admin_url('admin.php?page=aichat-livechat'),
			'meta' => [
				/* translators: %d: Number of chat sessions waiting for agent */
				'title' => sprintf(__('%d chat sessions waiting', 'axiachat-ai'), $pending),
				'class' => 'aichat-livechat-adminbar',
			],
		]);
	}
}

/**
 * Admin bar styles
 */
add_action('admin_head', 'aichat_livechat_admin_bar_styles');

function aichat_livechat_admin_bar_styles()
{
	if (! current_user_can('manage_options')) {
		return;
	}
	?>
    <style>
    #wpadminbar .aichat-livechat-adminbar .ab-icon:before {
        content: "\f125";
        top: 3px;
    }
    #wpadminbar .aichat-livechat-badge {
        display: inline-block;
        background: #d63638;
        color: #fff;
        font-size: 10px;
        line-height: 16px;
        min-width: 16px;
        text-align: center;
        border-radius: 8px;
        padding: 0 5px;
        margin-left: 4px;
    }
    </style>
    <?php
}

/**
 * Render settings content (for embedded tab)
 */
function aichat_livechat_render_settings_content()
{
	$settings = AIChat_LiveChat_Manager::get_settings();
	$days = [
		1 => __('Monday', 'axiachat-ai'),
		2 => __('Tuesday', 'axiachat-ai'),
		3 => __('Wednesday', 'axiachat-ai'),
		4 => __('Thursday', 'axiachat-ai'),
		5 => __('Friday', 'axiachat-ai'),
		6 => __('Saturday', 'axiachat-ai'),
		0 => __('Sunday', 'axiachat-ai'),
	];

	// Get external destination
	$external_destination = get_option('aichat_livechat_external_destination', '');
	$availability_mode = get_option('aichat_livechat_availability_mode', 'manual');
	$working_hours = get_option('aichat_livechat_working_hours', []);
	?>
    <form method="post" id="aichat-livechat-settings-form">
        <input type="hidden" name="action" value="aichat_livechat_save_settings" />
        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('aichat_livechat_admin_nonce')); ?>" />
        
        <!-- 1. Destinations Card -->
        <div class="card shadow-sm mb-4 card100">
            <div class="card-header bg-light d-flex align-items-center">
                <i class="bi bi-diagram-3 me-2"></i>
                <strong><?php esc_html_e('Destinations', 'axiachat-ai'); ?></strong>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><?php esc_html_e('Choose where agents can manage Live Chat. The WordPress backend is always active. You can enable one additional external destination.', 'axiachat-ai'); ?></p>
                
                <div class="row">
                    <!-- WordPress Backend (always enabled) -->
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-primary">
                            <div class="card-body text-center p-3">
                                <div class="mb-2">
                                    <span class="dashicons dashicons-wordpress" style="font-size:32px;color:#21759b;"></span>
                                </div>
                                <h6 class="mb-1"><?php esc_html_e('WordPress Backend', 'axiachat-ai'); ?></h6>
                                <small class="text-muted d-block mb-2"><?php esc_html_e('Dashboard panel for agents', 'axiachat-ai'); ?></small>
                                <span class="badge bg-success"><?php esc_html_e('Always Active', 'axiachat-ai'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Slack -->
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 destination-card-selectable <?php echo $external_destination === 'slack' ? 'border-primary' : ''; ?>" data-destination="slack">
                            <div class="card-body text-center p-3" style="cursor:pointer;">
                                <div class="mb-2">
                                    <i class="bi bi-slack" style="font-size:32px;color:#4A154B;"></i>
                                </div>
                                <h6 class="mb-1"><?php esc_html_e('Slack', 'axiachat-ai'); ?></h6>
                                <small class="text-muted d-block mb-2"><?php esc_html_e('Receive & reply from Slack', 'axiachat-ai'); ?></small>
                                <div class="form-check d-flex justify-content-center">
                                    <input class="form-check-input" type="radio" name="aichat_livechat_external_destination" 
                                           id="dest_slack" value="slack" <?php checked($external_destination, 'slack'); ?>>
                                    <label class="form-check-label ms-1" for="dest_slack"><?php esc_html_e('Enable', 'axiachat-ai'); ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Telegram -->
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 destination-card-selectable <?php echo $external_destination === 'telegram' ? 'border-primary' : ''; ?>" data-destination="telegram">
                            <div class="card-body text-center p-3" style="cursor:pointer;">
                                <div class="mb-2">
                                    <i class="bi bi-telegram" style="font-size:32px;color:#0088cc;"></i>
                                </div>
                                <h6 class="mb-1"><?php esc_html_e('Telegram', 'axiachat-ai'); ?></h6>
                                <small class="text-muted d-block mb-2"><?php esc_html_e('Receive & reply from Telegram', 'axiachat-ai'); ?></small>
                                <div class="form-check d-flex justify-content-center">
                                    <input class="form-check-input" type="radio" name="aichat_livechat_external_destination" 
                                           id="dest_telegram" value="telegram" <?php checked($external_destination, 'telegram'); ?>>
                                    <label class="form-check-label ms-1" for="dest_telegram"><?php esc_html_e('Enable', 'axiachat-ai'); ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- None option (hidden) -->
                    <input type="hidden" id="dest_none_value" value="">
                </div>
                
                <div class="text-end mt-2">
                    <button type="button" class="btn btn-link btn-sm text-muted" id="clear-external-destination">
                        <i class="bi bi-x-circle me-1"></i><?php esc_html_e('Disable external destination', 'axiachat-ai'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- 2. Availability Card -->
        <div class="card shadow-sm mb-4 card100">
            <div class="card-header bg-light d-flex align-items-center">
                <i class="bi bi-clock me-2"></i>
                <strong><?php esc_html_e('Availability', 'axiachat-ai'); ?></strong>
            </div>
            <div class="card-body">
                <!-- Mode Selection -->
                <div class="mb-4">
                    <label class="form-label fw-bold"><?php esc_html_e('Availability Mode', 'axiachat-ai'); ?></label>
                    <div class="d-flex gap-4">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="aichat_livechat_availability_mode" 
                                   id="mode_manual" value="manual" <?php checked($availability_mode, 'manual'); ?>>
                            <label class="form-check-label" for="mode_manual">
                                <strong><?php esc_html_e('Manual', 'axiachat-ai'); ?></strong>
                                <small class="d-block text-muted"><?php esc_html_e('Toggle on/off manually', 'axiachat-ai'); ?></small>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="aichat_livechat_availability_mode" 
                                   id="mode_schedule" value="schedule" <?php checked($availability_mode, 'schedule'); ?>>
                            <label class="form-check-label" for="mode_schedule">
                                <strong><?php esc_html_e('Schedule', 'axiachat-ai'); ?></strong>
                                <small class="d-block text-muted"><?php esc_html_e('Automatic based on working hours', 'axiachat-ai'); ?></small>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Manual Mode Settings -->
                <div id="manual-mode-settings" class="<?php echo $availability_mode !== 'manual' ? 'd-none' : ''; ?>">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="manual_available" 
                               name="aichat_livechat_available" value="1" 
                               <?php checked(get_option('aichat_livechat_available', 1)); ?>>
                        <label class="form-check-label" for="manual_available">
                            <?php esc_html_e('Agents are currently available', 'axiachat-ai'); ?>
                        </label>
                    </div>
                </div>
                
                <!-- Schedule Mode Settings (Working Hours) -->
                <div id="schedule-mode-settings" class="<?php echo $availability_mode !== 'schedule' ? 'd-none' : ''; ?>">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        <?php esc_html_e('Configure the hours when agents are available. Outside these hours, visitors will see the offline message.', 'axiachat-ai'); ?>
                    </div>
                    
                    <div class="working-hours-container">
                        <?php foreach ($days as $day_num => $day_name) :
                        	$day_settings = $working_hours[ $day_num ] ?? ['enabled' => false, 'slots' => [['start' => '09:00', 'end' => '18:00']]];
                        	$slots = $day_settings['slots'] ?? [['start' => '09:00', 'end' => '18:00']];
                        	?>
                        <div class="day-row d-flex align-items-start gap-3 py-2 border-bottom <?php echo ! $day_settings['enabled'] ? 'opacity-50' : ''; ?>">
                            <div class="day-toggle" style="width:30px;">
                                <input type="checkbox" class="form-check-input"
                                       id="day-enabled-<?php echo esc_attr($day_num); ?>"
                                       name="working_hours[<?php echo esc_attr($day_num); ?>][enabled]"
                                       value="1" <?php checked($day_settings['enabled']); ?>
                                       onchange="this.closest('.day-row').classList.toggle('opacity-50', !this.checked)">
                            </div>
                            <div class="day-name" style="width:100px;">
                                <label for="day-enabled-<?php echo esc_attr($day_num); ?>" class="mb-0 fw-medium">
                                    <?php echo esc_html($day_name); ?>
                                </label>
                            </div>
                            <div class="time-slots-container flex-grow-1">
                                <div class="time-slots-list">
                                    <?php foreach ($slots as $slot_idx => $slot) : ?>
                                    <div class="time-slot-row d-flex gap-2 align-items-center mb-2">
                                        <input type="time" class="form-control form-control-sm" style="width:110px;"
                                               name="working_hours[<?php echo esc_attr($day_num); ?>][slots][<?php echo esc_attr($slot_idx); ?>][start]"
                                               value="<?php echo esc_attr($slot['start']); ?>">
                                        <span class="text-muted"><?php esc_html_e('to', 'axiachat-ai'); ?></span>
                                        <input type="time" class="form-control form-control-sm" style="width:110px;"
                                               name="working_hours[<?php echo esc_attr($day_num); ?>][slots][<?php echo esc_attr($slot_idx); ?>][end]"
                                               value="<?php echo esc_attr($slot['end']); ?>">
                                        <?php if (count($slots) > 1) : ?>
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-remove-time-slot" title="<?php esc_attr_e('Remove', 'axiachat-ai'); ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-outline-secondary btn-sm btn-add-time-slot" data-day="<?php echo esc_attr($day_num); ?>">
                                    <i class="bi bi-plus me-1"></i><?php esc_html_e('Add slot', 'axiachat-ai'); ?>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            <?php esc_html_e('Add multiple time slots per day for split schedules (e.g., 9:00-13:00 and 16:00-20:00).', 'axiachat-ai'); ?>
                        </small>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <!-- Common settings -->
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label" for="aichat_livechat_estimated_wait"><?php esc_html_e('Estimated Wait Time', 'axiachat-ai'); ?></label>
                        <div class="input-group" style="max-width:200px;">
                            <input type="number" class="form-control" name="aichat_livechat_estimated_wait" 
                                   id="aichat_livechat_estimated_wait" 
                                   value="<?php echo esc_attr(get_option('aichat_livechat_estimated_wait', 2)); ?>" 
                                   min="1" max="60">
                            <span class="input-group-text"><?php esc_html_e('min', 'axiachat-ai'); ?></span>
                        </div>
                        <small class="text-muted"><?php esc_html_e('Per queue position', 'axiachat-ai'); ?></small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="aichat_livechat_agent_timeout"><?php esc_html_e('Agent Timeout', 'axiachat-ai'); ?></label>
                        <div class="input-group" style="max-width:200px;">
                            <input type="number" class="form-control" name="aichat_livechat_agent_timeout" 
                                   id="aichat_livechat_agent_timeout" 
                                   value="<?php echo esc_attr(get_option('aichat_livechat_agent_timeout', 5)); ?>" 
                                   min="1" max="60">
                            <span class="input-group-text"><?php esc_html_e('min', 'axiachat-ai'); ?></span>
                        </div>
                        <small class="text-muted"><?php esc_html_e('Returns to bot if no response', 'axiachat-ai'); ?></small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 3. Messages Card -->
        <div class="card shadow-sm mb-4 card100">
            <div class="card-header bg-light d-flex align-items-center">
                <i class="bi bi-chat-text me-2"></i>
                <strong><?php esc_html_e('Messages', 'axiachat-ai'); ?></strong>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="aichat_livechat_queue_message"><?php esc_html_e('Queue Message', 'axiachat-ai'); ?></label>
                    <textarea name="aichat_livechat_queue_message" id="aichat_livechat_queue_message" rows="2" class="form-control"><?php echo esc_textarea($settings['queue_message']); ?></textarea>
                    <small class="text-muted"><?php esc_html_e('Shown when visitor enters the queue', 'axiachat-ai'); ?></small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label" for="aichat_livechat_welcome_message"><?php esc_html_e('Agent Welcome Message', 'axiachat-ai'); ?></label>
                    <textarea name="aichat_livechat_welcome_message" id="aichat_livechat_welcome_message" rows="2" class="form-control"><?php echo esc_textarea($settings['welcome_message']); ?></textarea>
                    <small class="text-muted"><?php esc_html_e('Sent when agent takes the session', 'axiachat-ai'); ?></small>
                </div>
                
                <div class="mb-0">
                    <label class="form-label" for="aichat_livechat_offline_message"><?php esc_html_e('Offline Message', 'axiachat-ai'); ?></label>
                    <textarea name="aichat_livechat_offline_message" id="aichat_livechat_offline_message" rows="2" class="form-control"><?php echo esc_textarea($settings['offline_message']); ?></textarea>
                    <small class="text-muted"><?php esc_html_e('Shown when no agents are available', 'axiachat-ai'); ?></small>
                </div>
            </div>
        </div>
        
        <!-- 4. Auto Handoff Card -->
        <div class="card shadow-sm mb-4 card100">
            <div class="card-header bg-light d-flex align-items-center">
                <i class="bi bi-arrow-left-right me-2"></i>
                <strong><?php esc_html_e('Auto Handoff', 'axiachat-ai'); ?></strong>
            </div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="auto_handoff" 
                           name="aichat_livechat_auto_handoff" value="1" <?php checked($settings['auto_handoff']); ?>>
                    <label class="form-check-label" for="auto_handoff">
                        <?php esc_html_e('Automatically trigger handoff when visitor uses certain keywords', 'axiachat-ai'); ?>
                    </label>
                </div>
                
                <div class="mb-0">
                    <label class="form-label" for="aichat_livechat_handoff_keywords"><?php esc_html_e('Handoff Keywords', 'axiachat-ai'); ?></label>
                    <textarea name="aichat_livechat_handoff_keywords" id="aichat_livechat_handoff_keywords" rows="2" class="form-control"><?php echo esc_textarea($settings['handoff_keywords']); ?></textarea>
                    <small class="text-muted"><?php esc_html_e('Comma-separated list (e.g., human, agent, help me, speak to someone)', 'axiachat-ai'); ?></small>
                </div>
            </div>
        </div>
        
        <!-- 5. Slack Integration Card (shown when Slack is selected) -->
        <div id="slack-settings-card" class="card shadow-sm mb-4 card100 <?php echo $external_destination !== 'slack' ? 'd-none' : ''; ?>">
            <div class="card-header bg-light d-flex align-items-center">
                <i class="bi bi-slack me-2"></i>
                <strong><?php esc_html_e('Slack Configuration', 'axiachat-ai'); ?></strong>
                <a href="<?php echo esc_url(admin_url('admin.php?page=aichat-livechat&tab=help')); ?>" class="ms-auto btn btn-link btn-sm">
                    <i class="bi bi-question-circle me-1"></i><?php esc_html_e('Setup Guide', 'axiachat-ai'); ?>
                </a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="aichat_livechat_slack_webhook_url"><?php esc_html_e('Webhook URL', 'axiachat-ai'); ?> <span class="text-danger">*</span></label>
                        <input type="url" class="form-control" name="aichat_livechat_slack_webhook_url" 
                               id="aichat_livechat_slack_webhook_url" 
                               value="<?php echo esc_attr($settings['slack_webhook_url']); ?>" 
                               placeholder="https://hooks.slack.com/services/...">
                        <small class="text-muted"><?php esc_html_e('From Incoming Webhooks in your Slack App', 'axiachat-ai'); ?></small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="aichat_livechat_slack_channel"><?php esc_html_e('Channel', 'axiachat-ai'); ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="aichat_livechat_slack_channel" 
                               id="aichat_livechat_slack_channel" 
                               value="<?php echo esc_attr($settings['slack_channel']); ?>" 
                               placeholder="#support">
                        <small class="text-muted"><?php esc_html_e('Channel name or ID for notifications', 'axiachat-ai'); ?></small>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label" for="aichat_livechat_slack_bot_token"><?php esc_html_e('Bot Token', 'axiachat-ai'); ?></label>
                    <input type="password" class="form-control" name="aichat_livechat_slack_bot_token" 
                           id="aichat_livechat_slack_bot_token" 
                           value="<?php echo esc_attr($settings['slack_bot_token']); ?>" 
                           placeholder="xoxb-..." style="max-width:400px;">
                    <small class="text-muted"><?php esc_html_e('Required for Quick Reply and bidirectional messaging. Get it from OAuth & Permissions.', 'axiachat-ai'); ?></small>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="slack_send_history" 
                           name="aichat_livechat_slack_send_history" value="1" <?php checked($settings['slack_send_history'] ?? false); ?>>
                    <label class="form-check-label" for="slack_send_history">
                        <?php esc_html_e('Include conversation history in notifications', 'axiachat-ai'); ?>
                    </label>
                </div>
                
                <div class="bg-light p-3 rounded">
                    <label class="form-label small text-muted mb-2"><?php esc_html_e('Interactivity URL (paste in your Slack App)', 'axiachat-ai'); ?></label>
                    <?php $slack_endpoint = rest_url('aichat/v1/livechat/slack'); ?>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-sm bg-white" value="<?php echo esc_url($slack_endpoint); ?>" readonly>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="navigator.clipboard.writeText('<?php echo esc_js($slack_endpoint); ?>'); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy', 2000);">Copy</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 5b. Telegram Configuration Card -->
        <?php $telegram_endpoint = rest_url('aichat/v1/livechat/telegram'); ?>
        <div id="telegram-settings-card" class="card shadow-sm mb-4 card100 <?php echo $external_destination !== 'telegram' ? 'd-none' : ''; ?>">
            <div class="card-header bg-light d-flex align-items-center">
                <i class="bi bi-telegram me-2"></i>
                <strong><?php esc_html_e('Telegram Configuration', 'axiachat-ai'); ?></strong>
            </div>
            <div class="card-body">
                <div class="alert alert-info small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    <?php esc_html_e('Create a Telegram bot via @BotFather and set up the webhook. See the Help tab for detailed instructions.', 'axiachat-ai'); ?>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="aichat_livechat_telegram_bot_token"><?php esc_html_e('Bot Token', 'axiachat-ai'); ?> <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="aichat_livechat_telegram_bot_token" 
                               id="aichat_livechat_telegram_bot_token" 
                               value="<?php echo esc_attr(get_option('aichat_livechat_telegram_bot_token', '')); ?>" 
                               placeholder="123456789:ABC-DEF...">
                        <small class="text-muted"><?php esc_html_e('Get it from @BotFather when creating your bot', 'axiachat-ai'); ?></small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="aichat_livechat_telegram_chat_id"><?php esc_html_e('Chat ID', 'axiachat-ai'); ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="aichat_livechat_telegram_chat_id" 
                               id="aichat_livechat_telegram_chat_id" 
                               value="<?php echo esc_attr(get_option('aichat_livechat_telegram_chat_id', '')); ?>" 
                               placeholder="-1001234567890">
                        <small class="text-muted"><?php esc_html_e('Your group/channel chat ID (use @userinfobot or similar)', 'axiachat-ai'); ?></small>
                    </div>
                </div>
                
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="telegram_send_history" 
                           name="aichat_livechat_telegram_send_history" value="1" <?php checked(get_option('aichat_livechat_telegram_send_history', false)); ?>>
                    <label class="form-check-label" for="telegram_send_history">
                        <?php esc_html_e('Include conversation history in notifications', 'axiachat-ai'); ?>
                    </label>
                </div>
                
                <div class="bg-light p-3 rounded mb-3">
                    <label class="form-label small text-muted mb-2"><?php esc_html_e('Webhook URL (set via Telegram API)', 'axiachat-ai'); ?></label>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-sm bg-white" value="<?php echo esc_url($telegram_endpoint); ?>" readonly>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="navigator.clipboard.writeText('<?php echo esc_js($telegram_endpoint); ?>'); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy', 2000);">Copy</button>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="telegram-set-webhook">
                        <i class="bi bi-link-45deg me-1"></i><?php esc_html_e('Set Webhook', 'axiachat-ai'); ?>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="telegram-test-connection">
                        <i class="bi bi-check-circle me-1"></i><?php esc_html_e('Test Connection', 'axiachat-ai'); ?>
                    </button>
                </div>
                <div id="telegram-test-result" class="mt-2"></div>
            </div>
        </div>
        
        <!-- 6. Webhook Integration Card -->
        <div class="card shadow-sm mb-4 card100">
            <div class="card-header bg-light d-flex align-items-center">
                <i class="bi bi-broadcast me-2"></i>
                <strong><?php esc_html_e('Webhook Integration', 'axiachat-ai'); ?></strong>
                <span class="badge bg-secondary ms-2"><?php esc_html_e('Optional', 'axiachat-ai'); ?></span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><?php esc_html_e('Send handoff data to external services (n8n, Zapier, Make, etc.)', 'axiachat-ai'); ?></p>
                
                <div class="mb-3">
                    <label class="form-label" for="aichat_livechat_webhook_url"><?php esc_html_e('Webhook URL', 'axiachat-ai'); ?></label>
                    <input type="url" class="form-control" name="aichat_livechat_webhook_url" 
                           id="aichat_livechat_webhook_url" 
                           value="<?php echo esc_attr(get_option('aichat_livechat_webhook_url', '')); ?>" 
                           placeholder="https://your-instance.com/webhook/..." style="max-width:500px;">
                    <small class="text-muted"><?php esc_html_e('Leave empty to disable', 'axiachat-ai'); ?></small>
                </div>
                
                <div class="alert alert-light border small mb-0">
                    <strong><i class="bi bi-code-slash me-1"></i><?php esc_html_e('JSON Payload Format:', 'axiachat-ai'); ?></strong>
                    <pre class="mb-0 mt-2" style="font-size: 12px;"><code>{
  "session_id": "abc123-def456",
  "visitor_name": "John Doe",
  "visitor_email": "john@example.com",
  "page_url": "https://yoursite.com/contact",
  "conversation_history": [...],
  "handoff_reason": "User requested human agent",
  "timestamp": "2026-01-26T14:30:00Z",
  "source": "axiachat-livechat"
}</code></pre>
                </div>
            </div>
        </div>
        
        <!-- 7. Notifications Card -->
        <div class="card shadow-sm mb-4 card100">
            <div class="card-header bg-light d-flex align-items-center">
                <i class="bi bi-bell me-2"></i>
                <strong><?php esc_html_e('Notifications', 'axiachat-ai'); ?></strong>
            </div>
            <div class="card-body">
                <!-- Sound notification -->
                <div class="form-check form-switch mb-3 pb-3 border-bottom">
                    <input class="form-check-input" type="checkbox" id="notification_sound" 
                           name="aichat_livechat_notification_sound" value="1" <?php checked($settings['notification_sound']); ?>>
                    <label class="form-check-label" for="notification_sound">
                        <?php esc_html_e('Play sound when new messages arrive (in dashboard)', 'axiachat-ai'); ?>
                    </label>
                </div>
                
                <!-- Email notification -->
                <div class="row mb-3 pb-3 border-bottom">
                    <div class="col-md-8">
                        <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                            <input type="checkbox" name="aichat_livechat_email_notify_enabled" id="email_notify_enabled" value="1" 
                                   <?php checked(get_option('aichat_livechat_email_notify_enabled', false)); ?>>
                            <strong><?php esc_html_e('Send email notification when a new handoff request arrives', 'axiachat-ai'); ?></strong>
                        </label>
                    </div>
                    <div class="col-md-4 text-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#livechatEmailTemplateModal">
                            <i class="bi bi-pencil me-1"></i><?php esc_html_e('Edit Template', 'axiachat-ai'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="row">
                    <label for="email_notify_address" class="col-md-3 col-form-label"><?php esc_html_e('Notification Email', 'axiachat-ai'); ?></label>
                    <div class="col-md-9">
                        <input type="email" class="form-control" name="aichat_livechat_email_notify_address" id="email_notify_address"
                               value="<?php echo esc_attr(get_option('aichat_livechat_email_notify_address', '')); ?>" 
                               placeholder="<?php echo esc_attr(get_option('admin_email')); ?>"
                               style="max-width: 400px;">
                        <small class="text-muted"><?php esc_html_e('Leave empty to use the site admin email.', 'axiachat-ai'); ?></small>
                    </div>
                </div>
            </div>
        </div>
        
        <?php submit_button('Save Settings'); ?>
    </form>
    
    <!-- LiveChat Email Template Modal -->
    <div class="modal fade" id="livechatEmailTemplateModal" tabindex="-1" aria-labelledby="livechatEmailTemplateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="livechatEmailTemplateModalLabel">
                        <i class="bi bi-envelope me-2"></i><?php esc_html_e('LiveChat Notification Email Template', 'axiachat-ai'); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="livechat_email_subject" class="form-label"><?php esc_html_e('Email Subject', 'axiachat-ai'); ?></label>
                        <input type="text" class="form-control" id="livechat_email_subject" name="livechat_email_subject"
                               value="<?php echo esc_attr(get_option('aichat_livechat_email_subject', __('New Live Chat Request: {visitor_name}', 'axiachat-ai'))); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="livechat_email_body" class="form-label"><?php esc_html_e('Email Body', 'axiachat-ai'); ?></label>
                        <textarea class="form-control" id="livechat_email_body" name="livechat_email_body" rows="12"><?php
                        		echo esc_textarea(get_option('aichat_livechat_email_body', __("A new Live Chat request needs attention:\n\nVisitor: {visitor_name}\nPage: {page_url}\n\nLast message:\n{last_message}\n\nHandoff reason: {handoff_reason}\n\nDate: {date}\n\n---\nRespond in the dashboard: {dashboard_url}", 'axiachat-ai')));
	?></textarea>
                    </div>
                    <div class="alert alert-info small">
                        <strong><?php esc_html_e('Available placeholders:', 'axiachat-ai'); ?></strong><br>
                        <code>{visitor_name}</code>, <code>{visitor_email}</code>, <code>{page_url}</code>, <code>{last_message}</code>, <code>{handoff_reason}</code>, <code>{date}</code>, <code>{dashboard_url}</code>, <code>{conversation_history}</code>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php esc_html_e('Close', 'axiachat-ai'); ?></button>
                    <button type="button" class="btn btn-primary" id="save-livechat-email-template"><?php esc_html_e('Save Template', 'axiachat-ai'); ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Toggle availability mode sections
        $('input[name="aichat_livechat_availability_mode"]').on('change', function() {
            var mode = $(this).val();
            if (mode === 'manual') {
                $('#manual-mode-settings').removeClass('d-none');
                $('#schedule-mode-settings').addClass('d-none');
            } else {
                $('#manual-mode-settings').addClass('d-none');
                $('#schedule-mode-settings').removeClass('d-none');
            }
        });
        
        // Toggle Slack/Telegram settings visibility
        $('input[name="aichat_livechat_external_destination"]').on('change', function() {
            var dest = $(this).val();
            
            // Hide all settings cards
            $('#slack-settings-card').addClass('d-none');
            $('#telegram-settings-card').addClass('d-none');
            
            // Show the appropriate one
            if (dest === 'slack') {
                $('#slack-settings-card').removeClass('d-none');
            } else if (dest === 'telegram') {
                $('#telegram-settings-card').removeClass('d-none');
            }
            
            // Update card borders
            $('.destination-card-selectable').removeClass('border-primary');
            $(this).closest('.destination-card-selectable').addClass('border-primary');
        });
        
        // Clear external destination
        $('#clear-external-destination').on('click', function() {
            $('input[name="aichat_livechat_external_destination"]').prop('checked', false);
            $('.destination-card-selectable').removeClass('border-primary');
            $('#slack-settings-card').addClass('d-none');
            $('#telegram-settings-card').addClass('d-none');
        });
        
        // Click on destination card
        $('.destination-card-selectable').on('click', function(e) {
            if ($(e.target).is('input')) return;
            var $radio = $(this).find('input[type="radio"]');
            if (!$radio.prop('disabled')) {
                $radio.prop('checked', true).trigger('change');
            }
        });
        
        // Add time slot
        $(document).on('click', '.btn-add-time-slot', function() {
            var dayNum = $(this).data('day');
            var $list = $(this).siblings('.time-slots-list');
            var slotCount = $list.find('.time-slot-row').length;
            
            var html = '<div class="time-slot-row d-flex gap-2 align-items-center mb-2">' +
                '<input type="time" class="form-control form-control-sm" style="width:110px;" ' +
                'name="working_hours[' + dayNum + '][slots][' + slotCount + '][start]" value="09:00">' +
                '<span class="text-muted"><?php echo esc_js(__('to', 'axiachat-ai')); ?></span>' +
                '<input type="time" class="form-control form-control-sm" style="width:110px;" ' +
                'name="working_hours[' + dayNum + '][slots][' + slotCount + '][end]" value="18:00">' +
                '<button type="button" class="btn btn-outline-danger btn-sm btn-remove-time-slot" title="<?php echo esc_attr(__('Remove', 'axiachat-ai')); ?>">' +
                '<i class="bi bi-trash"></i></button>' +
                '</div>';
            
            $list.append(html);
            
            // Show remove button on first slot if now multiple
            if ($list.find('.time-slot-row').length > 1) {
                $list.find('.time-slot-row').each(function() {
                    if (!$(this).find('.btn-remove-time-slot').length) {
                        $(this).append('<button type="button" class="btn btn-outline-danger btn-sm btn-remove-time-slot" title="<?php echo esc_attr(__('Remove', 'axiachat-ai')); ?>"><i class="bi bi-trash"></i></button>');
                    }
                });
            }
        });
        
        // Remove time slot
        $(document).on('click', '.btn-remove-time-slot', function() {
            var $list = $(this).closest('.time-slots-list');
            $(this).closest('.time-slot-row').remove();
            
            // Hide remove button if only one slot left
            if ($list.find('.time-slot-row').length === 1) {
                $list.find('.btn-remove-time-slot').remove();
            }
        });
        
        // Save email template
        $('#save-livechat-email-template').on('click', function() {
            var $btn = $(this);
            var originalText = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> <?php echo esc_js(__('Saving...', 'axiachat-ai')); ?>');
            
            $.post(ajaxurl, {
                action: 'aichat_livechat_save_email_template',
                nonce: '<?php echo esc_js(wp_create_nonce('aichat_livechat_admin_nonce')); ?>',
                subject: $('#livechat_email_subject').val(),
                body: $('#livechat_email_body').val()
            }, function(response) {
                $btn.prop('disabled', false).html(originalText);
                if (response.success) {
                    $('#livechatEmailTemplateModal').modal('hide');
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Error saving template', 'axiachat-ai')); ?>');
                }
            }).fail(function() {
                $btn.prop('disabled', false).html(originalText);
                alert('<?php echo esc_js(__('Connection error', 'axiachat-ai')); ?>');
            });
        });
        
        // Telegram: Set Webhook
        $('#telegram-set-webhook').on('click', function() {
            var $btn = $(this);
            var $result = $('#telegram-test-result');
            var token = $('#aichat_livechat_telegram_bot_token').val();
            var webhookUrl = '<?php echo esc_js(rest_url('aichat/v1/livechat/telegram')); ?>';
            
            if (!token) {
                $result.html('<div class="alert alert-warning py-2"><?php echo esc_js(__('Please enter the Bot Token first.', 'axiachat-ai')); ?></div>');
                return;
            }
            
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
            $result.html('<div class="text-muted small"><?php echo esc_js(__('Setting webhook...', 'axiachat-ai')); ?></div>');
            
            $.post(ajaxurl, {
                action: 'aichat_livechat_telegram_set_webhook',
                nonce: '<?php echo esc_js(wp_create_nonce('aichat_livechat_admin_nonce')); ?>',
                bot_token: token,
                webhook_url: webhookUrl
            }, function(response) {
                $btn.prop('disabled', false).html('<i class="bi bi-link-45deg me-1"></i><?php echo esc_js(__('Set Webhook', 'axiachat-ai')); ?>');
                if (response.success) {
                    $result.html('<div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i>' + response.data.message + '</div>');
                } else {
                    $result.html('<div class="alert alert-danger py-2"><i class="bi bi-x-circle me-1"></i>' + (response.data.message || '<?php echo esc_js(__('Error setting webhook', 'axiachat-ai')); ?>') + '</div>');
                }
            }).fail(function() {
                $btn.prop('disabled', false).html('<i class="bi bi-link-45deg me-1"></i><?php echo esc_js(__('Set Webhook', 'axiachat-ai')); ?>');
                $result.html('<div class="alert alert-danger py-2"><?php echo esc_js(__('Connection error', 'axiachat-ai')); ?></div>');
            });
        });
        
        // Telegram: Test Connection
        $('#telegram-test-connection').on('click', function() {
            var $btn = $(this);
            var $result = $('#telegram-test-result');
            var token = $('#aichat_livechat_telegram_bot_token').val();
            var chatId = $('#aichat_livechat_telegram_chat_id').val();
            
            if (!token || !chatId) {
                $result.html('<div class="alert alert-warning py-2"><?php echo esc_js(__('Please enter both Bot Token and Chat ID first.', 'axiachat-ai')); ?></div>');
                return;
            }
            
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
            $result.html('<div class="text-muted small"><?php echo esc_js(__('Testing connection...', 'axiachat-ai')); ?></div>');
            
            $.post(ajaxurl, {
                action: 'aichat_livechat_telegram_test',
                nonce: '<?php echo esc_js(wp_create_nonce('aichat_livechat_admin_nonce')); ?>',
                bot_token: token,
                chat_id: chatId
            }, function(response) {
                $btn.prop('disabled', false).html('<i class="bi bi-check-circle me-1"></i><?php echo esc_js(__('Test Connection', 'axiachat-ai')); ?>');
                if (response.success) {
                    $result.html('<div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i>' + response.data.message + '</div>');
                } else {
                    $result.html('<div class="alert alert-danger py-2"><i class="bi bi-x-circle me-1"></i>' + (response.data.message || '<?php echo esc_js(__('Connection failed', 'axiachat-ai')); ?>') + '</div>');
                }
            }).fail(function() {
                $btn.prop('disabled', false).html('<i class="bi bi-check-circle me-1"></i><?php echo esc_js(__('Test Connection', 'axiachat-ai')); ?>');
                $result.html('<div class="alert alert-danger py-2"><?php echo esc_js(__('Connection error', 'axiachat-ai')); ?></div>');
            });
        });
    });
    </script>
    <?php
}

/**
 * Render Help content
 */
function aichat_livechat_render_help_content()
{
	$slack_endpoint = rest_url('aichat/v1/livechat/slack');
	?>
    <div class="row">
        <div class="col-lg-10">
            
            <!-- Overview Card -->
            <div class="card shadow-sm mb-4 card100">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong><?php esc_html_e('Live Chat Overview', 'axiachat-ai'); ?></strong>
                </div>
                <div class="card-body">
                    <p><?php esc_html_e('The Live Chat add-on allows human agents to take over conversations from the AI chatbot. When visitors request human assistance, agents can seamlessly continue the conversation.', 'axiachat-ai'); ?></p>
                    
                    <h6 class="fw-bold mt-4"><i class="bi bi-diagram-3 me-2"></i><?php esc_html_e('How It Works', 'axiachat-ai'); ?></h6>
                    <ol class="mb-0">
                        <li><?php esc_html_e('Visitor requests human help (via keywords or explicit request)', 'axiachat-ai'); ?></li>
                        <li><?php esc_html_e('Agents receive notification (in dashboard, Slack, or Telegram)', 'axiachat-ai'); ?></li>
                        <li><?php esc_html_e('Agent takes the session and chats directly with the visitor', 'axiachat-ai'); ?></li>
                        <li><?php esc_html_e('When finished, agent returns visitor to the AI bot', 'axiachat-ai'); ?></li>
                    </ol>
                </div>
            </div>
            
            <!-- Slack Setup Guide -->
            <div class="card shadow-sm mb-4 card100">
                <div class="card-header bg-light">
                    <i class="bi bi-slack me-2"></i>
                    <strong><?php esc_html_e('Slack Integration Setup', 'axiachat-ai'); ?></strong>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4"><?php esc_html_e('Follow these steps to enable Slack notifications and bidirectional communication for Live Chat.', 'axiachat-ai'); ?></p>
                    
                    <!-- Step 1 -->
                    <div class="mb-4 p-3 bg-light rounded">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-1-circle me-2"></i><?php esc_html_e('Create a Slack App', 'axiachat-ai'); ?></h6>
                        <ol class="mb-0">
                            <li><?php esc_html_e('Go to', 'axiachat-ai'); ?> <a href="https://api.slack.com/apps" target="_blank">api.slack.com/apps</a></li>
                            <li><?php esc_html_e('Click "Create New App" → "From scratch"', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Give it a name (e.g., "Live Chat") and select your workspace', 'axiachat-ai'); ?></li>
                        </ol>
                    </div>
                    
                    <!-- Step 2 -->
                    <div class="mb-4 p-3 bg-light rounded">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-2-circle me-2"></i><?php esc_html_e('Configure Incoming Webhook', 'axiachat-ai'); ?></h6>
                        <ol class="mb-0">
                            <li><?php esc_html_e('In your app settings, go to "Incoming Webhooks"', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Toggle "Activate Incoming Webhooks" to On', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Click "Add New Webhook to Workspace"', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Select the channel where you want notifications (e.g., #support)', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Copy the Webhook URL and paste it in Settings → Slack Webhook URL', 'axiachat-ai'); ?></li>
                        </ol>
                    </div>
                    
                    <!-- Step 3 -->
                    <div class="mb-4 p-3 bg-light rounded">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-3-circle me-2"></i><?php esc_html_e('Enable Interactivity (for buttons & replies)', 'axiachat-ai'); ?></h6>
                        <ol class="mb-2">
                            <li><?php esc_html_e('Go to "Interactivity & Shortcuts" in your app settings', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Toggle "Interactivity" to On', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Set the Request URL to:', 'axiachat-ai'); ?></li>
                        </ol>
                        <div class="d-flex align-items-center gap-2">
                            <code class="bg-white p-2 rounded flex-grow-1" style="word-break:break-all;"><?php echo esc_url($slack_endpoint); ?></code>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="navigator.clipboard.writeText('<?php echo esc_js($slack_endpoint); ?>'); this.textContent='Copied!'; setTimeout(() => this.textContent='Copy', 2000);">Copy</button>
                        </div>
                        <?php if (strpos($slack_endpoint, 'rest_route') !== false || strpos($slack_endpoint, '?rest_route=') !== false) : ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong><?php esc_html_e('Note:', 'axiachat-ai'); ?></strong>
                            <?php esc_html_e('Your site is using plain permalinks. Consider changing to "Post name" in Settings → Permalinks for better compatibility.', 'axiachat-ai'); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Step 4 -->
                    <div class="mb-4 p-3 bg-light rounded">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-4-circle me-2"></i><?php esc_html_e('Add Bot Token Scopes', 'axiachat-ai'); ?></h6>
                        <ol class="mb-2">
                            <li><?php esc_html_e('Go to "OAuth & Permissions" in your app settings', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Scroll to "Scopes" → "Bot Token Scopes"', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Add these scopes:', 'axiachat-ai'); ?></li>
                        </ol>
                        <div class="d-flex gap-2 flex-wrap mb-3">
                            <code class="bg-white px-2 py-1 rounded">chat:write</code>
                            <code class="bg-white px-2 py-1 rounded">users:read</code>
                        </div>
                        <p class="small text-muted mb-0">
                            <strong>chat:write:</strong> <?php esc_html_e('Allows posting messages to channels', 'axiachat-ai'); ?><br>
                            <strong>users:read:</strong> <?php esc_html_e('Identifies which agent is responding', 'axiachat-ai'); ?>
                        </p>
                    </div>
                    
                    <!-- Step 5 -->
                    <div class="mb-4 p-3 bg-light rounded">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-5-circle me-2"></i><?php esc_html_e('Install App & Get Bot Token', 'axiachat-ai'); ?></h6>
                        <ol class="mb-0">
                            <li><?php esc_html_e('Go to "Install App" in the sidebar', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Click "Install to Workspace" and authorize', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Copy the "Bot User OAuth Token" (starts with xoxb-)', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Paste it in Settings → Bot Token', 'axiachat-ai'); ?></li>
                        </ol>
                    </div>
                    
                    <!-- Step 6 -->
                    <div class="p-3 bg-light rounded">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-6-circle me-2"></i><?php esc_html_e('Invite Bot to Channel', 'axiachat-ai'); ?></h6>
                        <ol class="mb-0">
                            <li><?php esc_html_e('Open the Slack channel you selected for notifications', 'axiachat-ai'); ?></li>
                            <li><?php
							/* translators: %s: Slack command code (/invite @YourAppName) */
							printf(esc_html__('Type %s and select your app', 'axiachat-ai'), '<code>/invite @YourAppName</code>'); ?></li>
                            <li><?php esc_html_e('The bot can now send messages to this channel', 'axiachat-ai'); ?></li>
                        </ol>
                    </div>
                </div>
            </div>
            
            <!-- Settings Explanation -->
            <div class="card shadow-sm mb-4 card100">
                <div class="card-header bg-light">
                    <i class="bi bi-sliders me-2"></i>
                    <strong><?php esc_html_e('Settings Reference', 'axiachat-ai'); ?></strong>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th style="width:30%;"><?php esc_html_e('Setting', 'axiachat-ai'); ?></th>
                                <th><?php esc_html_e('Description', 'axiachat-ai'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong><?php esc_html_e('Availability Mode', 'axiachat-ai'); ?></strong></td>
                                <td><?php esc_html_e('Manual: Toggle on/off manually. Schedule: Automatic based on working hours.', 'axiachat-ai'); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('Estimated Wait Time', 'axiachat-ai'); ?></strong></td>
                                <td><?php esc_html_e('Minutes per queue position. If 2 visitors are waiting and value is 3, 3rd visitor sees ~6 min wait.', 'axiachat-ai'); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('Agent Timeout', 'axiachat-ai'); ?></strong></td>
                                <td><?php esc_html_e('If agent doesn\'t respond within this time, visitor automatically returns to the AI bot.', 'axiachat-ai'); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('Handoff Keywords', 'axiachat-ai'); ?></strong></td>
                                <td><?php esc_html_e('Comma-separated words that trigger automatic handoff (e.g., "human, agent, help me").', 'axiachat-ai'); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('Slack Channel', 'axiachat-ai'); ?></strong></td>
                                <td><?php esc_html_e('Channel ID or name (e.g., #support or C01234567). Required for visitor message forwarding.', 'axiachat-ai'); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e('Send Conversation History', 'axiachat-ai'); ?></strong></td>
                                <td><?php esc_html_e('When enabled, the Slack notification includes the previous conversation with the AI bot.', 'axiachat-ai'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Telegram Setup Guide -->
            <?php $telegram_endpoint = rest_url('aichat/v1/livechat/telegram'); ?>
            <div class="card shadow-sm mb-4 card100">
                <div class="card-header bg-light">
                    <i class="bi bi-telegram me-2"></i>
                    <strong><?php esc_html_e('Telegram Integration Setup', 'axiachat-ai'); ?></strong>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4"><?php esc_html_e('Follow these steps to enable Telegram notifications and bidirectional communication for Live Chat.', 'axiachat-ai'); ?></p>
                    
                    <!-- Step 1 -->
                    <div class="mb-4 p-3 bg-light rounded">
                        <h6 class="fw-bold text-info mb-2"><i class="bi bi-1-circle me-2"></i><?php esc_html_e('Create a Telegram Bot', 'axiachat-ai'); ?></h6>
                        <ol class="mb-0">
                            <li><?php esc_html_e('Open Telegram and search for', 'axiachat-ai'); ?> <code>@BotFather</code></li>
                            <li><?php esc_html_e('Send the command', 'axiachat-ai'); ?> <code>/newbot</code></li>
                            <li><?php esc_html_e('Choose a name for your bot (e.g., "My Site Live Chat")', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Choose a username ending in "bot" (e.g., "mysitelivechat_bot")', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Copy the Bot Token that BotFather gives you', 'axiachat-ai'); ?></li>
                        </ol>
                    </div>
                    
                    <!-- Step 2 -->
                    <div class="mb-4 p-3 bg-light rounded">
                        <h6 class="fw-bold text-info mb-2"><i class="bi bi-2-circle me-2"></i><?php esc_html_e('Create a Group/Channel for Notifications', 'axiachat-ai'); ?></h6>
                        <ol class="mb-0">
                            <li><?php esc_html_e('Create a new Telegram group (or use an existing one)', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Add your bot to the group as a member', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Make the bot an admin (required to receive messages)', 'axiachat-ai'); ?></li>
                        </ol>
                    </div>
                    
                    <!-- Step 3 -->
                    <div class="mb-4 p-3 bg-light rounded">
                        <h6 class="fw-bold text-info mb-2"><i class="bi bi-3-circle me-2"></i><?php esc_html_e('Get the Chat ID', 'axiachat-ai'); ?></h6>
                        <p class="mb-2"><?php esc_html_e('You need the Chat ID of your group. Here are two ways to get it:', 'axiachat-ai'); ?></p>
                        <div class="mb-3">
                            <strong><?php esc_html_e('Option A: Using @userinfobot', 'axiachat-ai'); ?></strong>
                            <ol class="mb-0">
                                <li><?php esc_html_e('Add @userinfobot to your group', 'axiachat-ai'); ?></li>
                                <li><?php esc_html_e('It will show the Chat ID (starts with - for groups)', 'axiachat-ai'); ?></li>
                                <li><?php esc_html_e('You can remove the bot after getting the ID', 'axiachat-ai'); ?></li>
                            </ol>
                        </div>
                        <div>
                            <strong><?php esc_html_e('Option B: Using the API', 'axiachat-ai'); ?></strong>
                            <ol class="mb-0">
                                <li><?php esc_html_e('Send a message in your group', 'axiachat-ai'); ?></li>
                                <li><?php esc_html_e('Open this URL in your browser (replace YOUR_BOT_TOKEN):', 'axiachat-ai'); ?><br>
                                    <code>https://api.telegram.org/botYOUR_BOT_TOKEN/getUpdates</code>
                                </li>
                                <li><?php esc_html_e('Find "chat":{"id": and copy the number (including the minus sign)', 'axiachat-ai'); ?></li>
                            </ol>
                        </div>
                    </div>
                    
                    <!-- Step 4 -->
                    <div class="mb-4 p-3 bg-light rounded">
                        <h6 class="fw-bold text-info mb-2"><i class="bi bi-4-circle me-2"></i><?php esc_html_e('Configure in AxiaChat', 'axiachat-ai'); ?></h6>
                        <ol class="mb-0">
                            <li><?php esc_html_e('Go to Settings tab and select Telegram as destination', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Paste your Bot Token', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Paste your Chat ID', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Click "Set Webhook" to register the webhook with Telegram', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Click "Test Connection" to verify everything works', 'axiachat-ai'); ?></li>
                        </ol>
                    </div>
                    
                    <!-- Step 5 -->
                    <div class="p-3 bg-light rounded">
                        <h6 class="fw-bold text-info mb-2"><i class="bi bi-5-circle me-2"></i><?php esc_html_e('Using the Integration', 'axiachat-ai'); ?></h6>
                        <p class="mb-2"><?php esc_html_e('Once configured:', 'axiachat-ai'); ?></p>
                        <ul class="mb-0">
                            <li><?php esc_html_e('You\'ll receive notifications in your group when visitors request human help', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Click "Take Chat" to start responding', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Reply to the notification message to send messages to the visitor', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Click "Return to Bot" when finished', 'axiachat-ai'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Troubleshooting -->
            <div class="card shadow-sm mb-4 card100">
                <div class="card-header bg-warning text-dark">
                    <i class="bi bi-tools me-2"></i>
                    <strong><?php esc_html_e('Troubleshooting', 'axiachat-ai'); ?></strong>
                </div>
                <div class="card-body">
                    <div class="accordion" id="troubleshootingAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ts1">
                                    <?php esc_html_e('Slack notifications not arriving', 'axiachat-ai'); ?>
                                </button>
                            </h2>
                            <div id="ts1" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                <div class="accordion-body">
                                    <ul class="mb-0">
                                        <li><?php esc_html_e('Verify "Enable Slack" is checked in Settings', 'axiachat-ai'); ?></li>
                                        <li><?php esc_html_e('Check that the Webhook URL is correct and complete', 'axiachat-ai'); ?></li>
                                        <li><?php esc_html_e('Make sure the webhook channel still exists', 'axiachat-ai'); ?></li>
                                        <li><?php esc_html_e('Check debug.log for error messages', 'axiachat-ai'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ts2">
                                    <?php esc_html_e('Buttons in Slack not working', 'axiachat-ai'); ?>
                                </button>
                            </h2>
                            <div id="ts2" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                <div class="accordion-body">
                                    <ul class="mb-0">
                                        <li><?php esc_html_e('Verify Interactivity is enabled in your Slack App', 'axiachat-ai'); ?></li>
                                        <li><?php esc_html_e('Check the Request URL matches your site\'s REST API endpoint', 'axiachat-ai'); ?></li>
                                        <li><?php esc_html_e('If using plain permalinks, ensure the URL format is correct', 'axiachat-ai'); ?></li>
                                        <li><?php esc_html_e('Reinstall the app to your workspace after making changes', 'axiachat-ai'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ts3">
                                    <?php esc_html_e('Quick Reply modal not opening', 'axiachat-ai'); ?>
                                </button>
                            </h2>
                            <div id="ts3" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                <div class="accordion-body">
                                    <ul class="mb-0">
                                        <li><?php esc_html_e('Ensure the Bot Token is configured (xoxb-...)', 'axiachat-ai'); ?></li>
                                        <li><?php esc_html_e('Verify the bot has chat:write scope', 'axiachat-ai'); ?></li>
                                        <li><?php esc_html_e('Check that the bot is invited to the channel', 'axiachat-ai'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ts4">
                                    <?php esc_html_e('Visitor messages not reaching Slack', 'axiachat-ai'); ?>
                                </button>
                            </h2>
                            <div id="ts4" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                <div class="accordion-body">
                                    <ul class="mb-0">
                                        <li><?php esc_html_e('The Slack Channel field must be filled (e.g., #support)', 'axiachat-ai'); ?></li>
                                        <li><?php esc_html_e('Agent must "Take" the session first for messages to forward', 'axiachat-ai'); ?></li>
                                        <li><?php esc_html_e('Verify the Bot Token has chat:write permission', 'axiachat-ai'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ts5">
                                    <?php esc_html_e('Telegram notifications not arriving', 'axiachat-ai'); ?>
                                </button>
                            </h2>
                            <div id="ts5" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                <div class="accordion-body">
                                    <ul class="mb-0">
                                        <li><?php esc_html_e('Verify Telegram is selected as the destination in Settings', 'axiachat-ai'); ?></li>
                                        <li><?php esc_html_e('Check that both Bot Token and Chat ID are configured', 'axiachat-ai'); ?></li>
                                        <li><?php esc_html_e('Make sure you clicked "Set Webhook" after entering the token', 'axiachat-ai'); ?></li>
                                        <li><?php esc_html_e('Verify your bot is a member (and admin) of the group', 'axiachat-ai'); ?></li>
                                        <li><?php esc_html_e('Use "Test Connection" to verify the configuration', 'axiachat-ai'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ts6">
                                    <?php esc_html_e('Telegram buttons not working', 'axiachat-ai'); ?>
                                </button>
                            </h2>
                            <div id="ts6" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                <div class="accordion-body">
                                    <ul class="mb-0">
                                        <li><?php esc_html_e('Ensure the webhook is set correctly (click "Set Webhook")', 'axiachat-ai'); ?></li>
                                        <li><?php esc_html_e('Your site must be accessible via HTTPS for Telegram webhooks', 'axiachat-ai'); ?></li>
                                        <li><?php esc_html_e('Check debug.log for any error messages from the webhook', 'axiachat-ai'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ts7">
                                    <?php esc_html_e('Telegram replies not reaching visitor', 'axiachat-ai'); ?>
                                </button>
                            </h2>
                            <div id="ts7" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion">
                                <div class="accordion-body">
                                    <ul class="mb-0">
                                        <li><?php esc_html_e('You must reply to the notification message (use Telegram\'s reply feature)', 'axiachat-ai'); ?></li>
                                        <li><?php esc_html_e('Make sure you clicked "Take Chat" first', 'axiachat-ai'); ?></li>
                                        <li><?php esc_html_e('Check that the session is still active (not returned to bot)', 'axiachat-ai'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    <?php
}
