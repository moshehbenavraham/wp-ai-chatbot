<?php
/**
 * Live Chat Settings Page
 *
 * @package AIChat
 * @subpackage LiveChat
 * @since 2.5.0
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Register settings submenu
 */
add_action('admin_menu', 'aichat_livechat_settings_menu', 30);

function aichat_livechat_settings_menu()
{
	add_submenu_page(
		null, // Hidden from menu, accessed via link from dashboard
		__('Live Chat Settings', 'axiachat-ai'),
		__('Live Chat Settings', 'axiachat-ai'),
		'manage_options',
		'aichat-livechat-settings',
		'aichat_livechat_render_settings'
	);
}

/**
 * Register settings
 */
add_action('admin_init', 'aichat_livechat_register_settings');

function aichat_livechat_register_settings()
{
	$options = [
		'aichat_livechat_available',
		'aichat_livechat_estimated_wait',
		'aichat_livechat_agent_timeout',
		'aichat_livechat_auto_handoff',
		'aichat_livechat_handoff_keywords',
		'aichat_livechat_slack_enabled',
		'aichat_livechat_slack_webhook_url',
		'aichat_livechat_slack_channel',
		'aichat_livechat_webhook_url',
		'aichat_livechat_offline_message',
		'aichat_livechat_welcome_message',
		'aichat_livechat_queue_message',
		'aichat_livechat_notification_sound',
	];

	foreach ($options as $option) {
		register_setting(
			'aichat_livechat_settings',
			$option,
			[
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
	}
}

/**
 * Render settings page
 */
function aichat_livechat_render_settings()
{
	$settings = AIChat_LiveChat_Manager::get_settings();
	?>
    <div class="wrap">
        <h1>
            <a href="<?php echo esc_url(admin_url('admin.php?page=aichat-livechat')); ?>" class="page-title-action" style="margin-right: 10px;">
                ← <?php echo esc_html__('Back to Dashboard', 'axiachat-ai'); ?>
            </a>
            <?php echo esc_html__('Live Chat Settings', 'axiachat-ai'); ?>
        </h1>
        
        <form method="post" action="options.php">
            <?php settings_fields('aichat_livechat_settings'); ?>
            
            <table class="form-table">
                <!-- Availability Section -->
                <tr>
                    <th colspan="2"><h2><?php echo esc_html__('Availability', 'axiachat-ai'); ?></h2></th>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Live Chat Available', 'axiachat-ai'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="aichat_livechat_available" value="1" <?php checked(get_option('aichat_livechat_available', 1)); ?> />
                            <?php echo esc_html__('Agents are available to take chats', 'axiachat-ai'); ?>
                        </label>
                        <p class="description"><?php echo esc_html__('Toggle off to prevent new escalations. Existing sessions will continue.', 'axiachat-ai'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="aichat_livechat_estimated_wait"><?php echo esc_html__('Estimated Wait Time', 'axiachat-ai'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="aichat_livechat_estimated_wait" id="aichat_livechat_estimated_wait" 
                               value="<?php echo esc_attr(get_option('aichat_livechat_estimated_wait', 2)); ?>" 
                               min="1" max="60" class="small-text" /> 
                        <?php echo esc_html__('minutes per queue position', 'axiachat-ai'); ?>
                        <p class="description"><?php echo esc_html__('Used to calculate estimated wait when multiple visitors are queued.', 'axiachat-ai'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="aichat_livechat_agent_timeout"><?php echo esc_html__('Agent Timeout', 'axiachat-ai'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="aichat_livechat_agent_timeout" id="aichat_livechat_agent_timeout" 
                               value="<?php echo esc_attr(get_option('aichat_livechat_agent_timeout', 5)); ?>" 
                               min="1" max="60" class="small-text" /> 
                        <?php echo esc_html__('minutes', 'axiachat-ai'); ?>
                        <p class="description"><?php echo esc_html__('If agent doesn\'t respond within this time, conversation returns to bot.', 'axiachat-ai'); ?></p>
                    </td>
                </tr>
                
                <!-- Messages Section -->
                <tr>
                    <th colspan="2"><h2><?php echo esc_html__('Messages', 'axiachat-ai'); ?></h2></th>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="aichat_livechat_queue_message"><?php echo esc_html__('Queue Message', 'axiachat-ai'); ?></label>
                    </th>
                    <td>
                        <textarea name="aichat_livechat_queue_message" id="aichat_livechat_queue_message" rows="2" class="large-text"><?php echo esc_textarea($settings['queue_message']); ?></textarea>
                        <p class="description"><?php echo esc_html__('Shown to visitors when they enter the agent queue.', 'axiachat-ai'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="aichat_livechat_welcome_message"><?php echo esc_html__('Agent Welcome Message', 'axiachat-ai'); ?></label>
                    </th>
                    <td>
                        <textarea name="aichat_livechat_welcome_message" id="aichat_livechat_welcome_message" rows="2" class="large-text"><?php echo esc_textarea($settings['welcome_message']); ?></textarea>
                        <p class="description"><?php echo esc_html__('Automatically sent when an agent takes the session.', 'axiachat-ai'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="aichat_livechat_offline_message"><?php echo esc_html__('Offline Message', 'axiachat-ai'); ?></label>
                    </th>
                    <td>
                        <textarea name="aichat_livechat_offline_message" id="aichat_livechat_offline_message" rows="2" class="large-text"><?php echo esc_textarea($settings['offline_message']); ?></textarea>
                        <p class="description"><?php echo esc_html__('Shown when no agents are available.', 'axiachat-ai'); ?></p>
                    </td>
                </tr>
                
                <!-- Auto Handoff Section -->
                <tr>
                    <th colspan="2"><h2><?php echo esc_html__('Auto Handoff', 'axiachat-ai'); ?></h2></th>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Keyword Detection', 'axiachat-ai'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="aichat_livechat_auto_handoff" value="1" <?php checked($settings['auto_handoff']); ?> />
                            <?php echo esc_html__('Automatically trigger handoff when visitor uses certain keywords', 'axiachat-ai'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="aichat_livechat_handoff_keywords"><?php echo esc_html__('Handoff Keywords', 'axiachat-ai'); ?></label>
                    </th>
                    <td>
                        <textarea name="aichat_livechat_handoff_keywords" id="aichat_livechat_handoff_keywords" rows="2" class="large-text"><?php echo esc_textarea($settings['handoff_keywords']); ?></textarea>
                        <p class="description"><?php echo esc_html__('Comma-separated list of keywords that trigger handoff.', 'axiachat-ai'); ?></p>
                    </td>
                </tr>
                
                <!-- Slack Integration Section -->
                <tr>
                    <th colspan="2"><h2><?php echo esc_html__('Slack Integration', 'axiachat-ai'); ?></h2></th>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Enable Slack', 'axiachat-ai'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="aichat_livechat_slack_enabled" value="1" <?php checked($settings['slack_enabled']); ?> />
                            <?php echo esc_html__('Send notifications to Slack when handoff is requested', 'axiachat-ai'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="aichat_livechat_slack_webhook_url"><?php echo esc_html__('Slack Webhook URL', 'axiachat-ai'); ?></label>
                    </th>
                    <td>
                        <input type="url" name="aichat_livechat_slack_webhook_url" id="aichat_livechat_slack_webhook_url" value="<?php echo esc_attr($settings['slack_webhook_url']); ?>" class="large-text" placeholder="https://hooks.slack.com/services/..." />
                        <p class="description">
                            <?php echo esc_html__('Create an Incoming Webhook at', 'axiachat-ai'); ?>
                            <a href="https://api.slack.com/apps" target="_blank">api.slack.com/apps</a>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="aichat_livechat_slack_channel"><?php echo esc_html__('Slack Channel', 'axiachat-ai'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="aichat_livechat_slack_channel" id="aichat_livechat_slack_channel" value="<?php echo esc_attr($settings['slack_channel']); ?>" class="regular-text" placeholder="#support" />
                        <p class="description"><?php echo esc_html__('Optional. Override the default channel from the webhook.', 'axiachat-ai'); ?></p>
                    </td>
                </tr>
                
                <!-- Notifications -->
                <tr>
                    <th colspan="2"><h2><?php echo esc_html__('Notifications', 'axiachat-ai'); ?></h2></th>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Sound Notifications', 'axiachat-ai'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="aichat_livechat_notification_sound" value="1" <?php checked($settings['notification_sound']); ?> />
                            <?php echo esc_html__('Play a sound when new messages arrive (in dashboard)', 'axiachat-ai'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
