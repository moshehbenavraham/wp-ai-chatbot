<?php
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Add-ons tab markup for AI Chat settings.
 * Uses variables prepared in parent scope: $connect_option, $connect_install_url,
 * $connect_install_required, $aichat_ai_tools_enabled_flag, $connect_active,
 * $connect_installed, $connect_version.
 */
?>
<div class="tab-pane" id="aichat-tab-addons" role="tabpanel" aria-labelledby="aichat-tab-link-addons" aria-hidden="true">
    <div class="row g-4">
        <div class="col-12">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-purple text-white d-flex align-items-center" style="background:#6f42c1;">
                    <i class="bi bi-puzzle-fill me-2"></i><strong><?php echo esc_html__('Add-ons', 'axiachat-ai'); ?></strong>
                </div>
                <div class="card-body">
                    <div class="aichat-checkbox-row mb-3">
                        <input type="hidden" name="aichat_addon_ai_tools_enabled" value="0" />
                        <label for="aichat_addon_ai_tools_enabled" class="aichat-checkbox-label">
                            <input type="checkbox" id="aichat_addon_ai_tools_enabled" name="aichat_addon_ai_tools_enabled" value="1" <?php checked((int) aichat_get_setting('aichat_addon_ai_tools_enabled'), 1); ?> />
                            <span><?php echo esc_html__('Enable AI Tools (tools & macros system)', 'axiachat-ai'); ?></span>
                        </label>
                        <div class="form-text ms-0"><?php echo esc_html__('When enabled, exposes the AI Tools menus and allows bots to call registered tools/macros.', 'axiachat-ai'); ?></div>
                    </div>
                    <?php $aichat_ai_tools_enabled_flag = (int) aichat_get_setting('aichat_addon_ai_tools_enabled'); ?>
                    <hr class="my-3" />
                    <div class="aichat-checkbox-row mb-0">
                        <input type="hidden" name="aichat_addon_leads_enabled" value="0" />
                        <label for="aichat_addon_leads_enabled" class="aichat-checkbox-label">
                            <input type="checkbox" id="aichat_addon_leads_enabled" name="aichat_addon_leads_enabled" value="1" <?php checked((int) get_option('aichat_addon_leads_enabled', 1), 1); ?> <?php disabled(! $aichat_ai_tools_enabled_flag); ?> />
                            <span><?php echo esc_html__('Enable Lead Capture', 'axiachat-ai'); ?></span>
                        </label>
                        <div class="form-text ms-0">
                            <?php
							if (! $aichat_ai_tools_enabled_flag) {
								echo esc_html__('Requires AI Tools enabled. Turn on AI Tools above to activate Lead Capture.', 'axiachat-ai');
							} else {
								echo esc_html__('Allows bots to capture customer contact information (name, email, phone) during conversations. Configure in AI Chat → Leads.', 'axiachat-ai');
							}
?>
                        </div>
                    </div>
                    <hr class="my-3" />
                    <div class="aichat-checkbox-row mb-0">
                        <input type="hidden" name="aichat_addon_appointments_enabled" value="0" />
                        <label for="aichat_addon_appointments_enabled" class="aichat-checkbox-label">
                            <input type="checkbox" id="aichat_addon_appointments_enabled" name="aichat_addon_appointments_enabled" value="1" <?php checked((int) get_option('aichat_addon_appointments_enabled', 1), 1); ?> <?php disabled(! $aichat_ai_tools_enabled_flag); ?> />
                            <span><?php echo esc_html__('Enable Appointments', 'axiachat-ai'); ?></span>
                        </label>
                        <div class="form-text ms-0">
                            <?php
if (! $aichat_ai_tools_enabled_flag) {
	echo esc_html__('Requires AI Tools enabled. Turn on AI Tools above to activate Appointments.', 'axiachat-ai');
} else {
	echo esc_html__('Allows bots to book appointments during conversations. Supports internal calendar, Google Calendar, Bookly, and Amelia. Configure in AI Chat → Appointments.', 'axiachat-ai');
}
?>
                        </div>
                    </div>
                    <hr class="my-3" />
                    <div class="aichat-checkbox-row mb-0">
                        <input type="hidden" name="aichat_addon_mcp_enabled" value="0" />
                        <label for="aichat_addon_mcp_enabled" class="aichat-checkbox-label">
                            <input type="checkbox" id="aichat_addon_mcp_enabled" name="aichat_addon_mcp_enabled" value="1" <?php checked((int) get_option('aichat_addon_mcp_enabled', 0), 1); ?> <?php disabled(! $aichat_ai_tools_enabled_flag); ?> />
                            <span><?php echo esc_html__('Enable MCP (Model Context Protocol)', 'axiachat-ai'); ?></span>
                        </label>
                        <div class="form-text ms-0">
                            <?php
if (! $aichat_ai_tools_enabled_flag) {
	echo esc_html__('Requires AI Tools enabled. Turn on AI Tools above to activate MCP integration.', 'axiachat-ai');
} else {
	echo esc_html__('Allows bots to connect to external MCP servers (Sentry, Notion, GitHub, etc.) and expose their tools. Configure servers in AI Chat → MCP Servers.', 'axiachat-ai');
}
?>
                        </div>
                    </div>
                    <hr class="my-3" />
                    <?php
					// LiveChat toggle — Professional plan only
					// Full toggle is stripped from free build by Freemius.
					if (function_exists('aichat_fs') && aichat_fs() && aichat_fs()->is__premium_only()) :
						// Check if LiveChat license is available (Professional plan only)
						$aichat_livechat_license = function_exists('aichat_can_use_livechat') ? aichat_can_use_livechat() : true;
						$aichat_livechat_locked = ! $aichat_livechat_license;
						$aichat_livechat_disabled = ! $aichat_ai_tools_enabled_flag || $aichat_livechat_locked;
						?>
                    <div class="aichat-checkbox-row mb-0" style="<?php echo $aichat_livechat_locked ? 'position:relative;' : ''; ?>">
                        <?php if ($aichat_livechat_locked) : ?>
                        <span class="position-absolute top-0 end-0 m-0 badge bg-gradient" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); font-size: 0.65rem; z-index: 10;">
                            <i class="bi bi-star-fill me-1"></i>Professional
                        </span>
                        <?php endif; ?>
                        <input type="hidden" name="aichat_addon_livechat_enabled" value="0" />
                        <label for="aichat_addon_livechat_enabled" class="aichat-checkbox-label">
                            <input type="checkbox" id="aichat_addon_livechat_enabled" name="aichat_addon_livechat_enabled" value="1" <?php checked((int) get_option('aichat_addon_livechat_enabled', 0), 1); ?> <?php disabled($aichat_livechat_disabled); ?> />
                            <span><?php echo esc_html__('Enable Live Chat (Human Handoff)', 'axiachat-ai'); ?></span>
                        </label>
                        <div class="form-text ms-0">
                            <?php
								if ($aichat_livechat_locked) {
									$aichat_upgrade_url = function_exists('aichat_fs') ? aichat_fs()->get_upgrade_url() : admin_url('admin.php?page=axiachat-ai-pricing');
									echo wp_kses_post(sprintf(
										/* translators: %s: upgrade link */
										__('<strong>Professional plan required.</strong> Live Chat allows human agents to take over AI conversations. <a href="%s">Upgrade now</a>', 'axiachat-ai'),
										esc_url($aichat_upgrade_url)
									));
								} elseif (! $aichat_ai_tools_enabled_flag) {
									echo esc_html__('Requires AI Tools enabled. Turn on AI Tools above to activate Live Chat.', 'axiachat-ai');
								} else {
									echo esc_html__('Allows bots to transfer conversations to human agents. Agents can respond via WP Dashboard or Slack. Configure in AI Chat → Live Chat.', 'axiachat-ai');
								}
?>
                        </div>
                    </div>
                    <?php else : ?>
                    <?php // Free build: show locked placeholder?>
                    <div class="aichat-checkbox-row mb-0" style="position:relative;">
                        <span class="position-absolute top-0 end-0 m-0 badge bg-gradient" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); font-size: 0.65rem; z-index: 10;">
                            <i class="bi bi-star-fill me-1"></i>Professional
                        </span>
                        <label class="aichat-checkbox-label">
                            <input type="checkbox" disabled />
                            <span><?php echo esc_html__('Enable Live Chat (Human Handoff)', 'axiachat-ai'); ?></span>
                        </label>
                        <div class="form-text ms-0">
                            <?php
$aichat_upgrade_url = function_exists('aichat_fs') && aichat_fs() ? aichat_fs()->get_upgrade_url() : admin_url('admin.php?page=axiachat-ai-pricing');
                    	echo wp_kses_post(sprintf(
                    		/* translators: %s: upgrade link */
                    		__('<strong>Professional plan required.</strong> Live Chat allows human agents to take over AI conversations. <a href="%s">Upgrade now</a>', 'axiachat-ai'),
                    		esc_url($aichat_upgrade_url)
                    	));
                    	?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <hr class="my-3" />
                    <div class="aichat-checkbox-row mb-0">
                        <input type="hidden" name="aichat_addon_connect_enabled" value="0" />
                        <label for="aichat_addon_connect_enabled" class="aichat-checkbox-label">
                            <input type="checkbox"
                                id="aichat_addon_connect_enabled"
                                name="aichat_addon_connect_enabled"
                                value="1"
                                <?php checked($connect_option, 1); ?>
                                <?php disabled(! $aichat_ai_tools_enabled_flag); ?>
                                data-guide-url="<?php echo esc_url($connect_install_url); ?>"
                                data-guide-required="<?php echo esc_attr($connect_install_required); ?>"
                                data-guide-message="<?php echo esc_attr__('Visit the installation guide for Andromeda Connect (WhatsApp & Telegram)?', 'axiachat-ai'); ?>"
                            />
                            <span><?php echo esc_html__('Enable WhatsApp & Telegram (Andromeda Connect)', 'axiachat-ai'); ?></span>
                        </label>
                        <div class="form-text ms-0">
                            <?php
                    	if (! $aichat_ai_tools_enabled_flag) {
                    		echo esc_html__('Requires AI Tools enabled. Turn on AI Tools above to manage external channel connectors.', 'axiachat-ai');
                    	} elseif ($connect_active) {
                    		echo esc_html__('Andromeda Connect is active. WhatsApp and Telegram routing are available.', 'axiachat-ai');
                    		if ($connect_version) {
                    			/* translators: %s: Detected Andromeda Connect plugin version. */
                    			echo ' ' . sprintf(esc_html__('(Version %s detected)', 'axiachat-ai'), esc_html($connect_version));
                    		}
                    	} elseif ($connect_installed) {
                    		echo esc_html__('Andromeda Connect files are present. Activate the plugin from Plugins → Installed Plugins or enable it here after activation.', 'axiachat-ai');
                    		if ($connect_version) {
                    			/* translators: %s: Detected Andromeda Connect plugin version. */
                    			echo ' ' . sprintf(esc_html__('(Version %s detected)', 'axiachat-ai'), esc_html($connect_version));
                    		}
                    	} else {
                    		printf(
                    			'%s <a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                    			esc_html__('Companion plugin for WhatsApp & Telegram integration.', 'axiachat-ai'),
                    			esc_url('https://github.com/estebanstifli/aichat-connect'),
                    			esc_html__('Click to view installation guide', 'axiachat-ai')
                    		);
                    	}
?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Widget Branding Card -->
    <?php
	// White Label / Branding — Standard plan and above
	// Full branding editor is stripped from free build by Freemius.
	if (function_exists('aichat_fs') && aichat_fs() && aichat_fs()->is__premium_only()) :
		// Check if user has White Label feature (Standard plan and above)
		$aichat_has_white_label = function_exists('aichat_has_feature') && defined('AICHAT_FEATURE_WHITE_LABEL')
			? aichat_has_feature(AICHAT_FEATURE_WHITE_LABEL)
			: false;

		// Get branding values with defaults
		$aichat_branding_enabled = get_option('aichat_branding_enabled', 1);
		$aichat_branding_text = get_option('aichat_branding_text', 'AxiaChat AI');
		$aichat_branding_url = get_option('aichat_branding_url', 'https://www.axiachat.org');
		$aichat_branding_icon = get_option('aichat_branding_icon', '⚡');
		?>
    <div class="row g-4 mt-2">
        <div class="col-12">
            <div class="card shadow-sm h-100<?php echo ! $aichat_has_white_label ? ' aichat-pro-locked' : ''; ?>" style="<?php echo ! $aichat_has_white_label ? 'opacity: 0.8;' : ''; ?>">
                <div class="card-header bg-secondary text-white d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-megaphone-fill me-2"></i><strong><?php echo esc_html__('Widget Branding', 'axiachat-ai'); ?></strong></span>
                    <?php if (! $aichat_has_white_label) : ?>
                        <span class="badge bg-warning text-dark">
                            <i class="bi bi-lock-fill me-1"></i><?php echo esc_html__('Standard', 'axiachat-ai'); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (! $aichat_has_white_label) : ?>
                        <div class="alert alert-warning mb-3 d-flex align-items-center">
                            <i class="bi bi-star-fill me-2"></i>
                            <div>
                                <strong><?php echo esc_html__('Upgrade to Standard', 'axiachat-ai'); ?></strong>
                                <?php echo esc_html__('to customize or remove the "Powered by" branding.', 'axiachat-ai'); ?>
                                <?php if (function_exists('aichat_fs') && aichat_fs()) : ?>
                                    <a href="<?php echo esc_url(aichat_fs()->get_upgrade_url()); ?>" class="btn btn-sm btn-warning ms-2">
                                        <?php echo esc_html__('Upgrade Now', 'axiachat-ai'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <p class="text-muted mb-3">
                        <?php echo esc_html__('Display a "Powered by" message at the bottom of the chat widget.', 'axiachat-ai'); ?>
                    </p>

                    <fieldset <?php disabled(! $aichat_has_white_label); ?>>
                        <div class="aichat-checkbox-row mb-3">
                            <input type="hidden" name="aichat_branding_enabled" value="0" />
                            <label for="aichat_branding_enabled" class="aichat-checkbox-label">
                                <input type="checkbox" 
                                       id="aichat_branding_enabled" 
                                       name="aichat_branding_enabled" 
                                       value="1" 
                                       <?php checked($aichat_branding_enabled); ?>
                                       <?php disabled(! $aichat_has_white_label); ?> />
                                <span><?php echo esc_html__('Show "Powered by" message in the widget', 'axiachat-ai'); ?></span>
                            </label>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="aichat_branding_text" class="form-label">
                                    <?php echo esc_html__('Brand Name', 'axiachat-ai'); ?>
                                </label>
                                <input type="text" 
                                       id="aichat_branding_text" 
                                       name="aichat_branding_text" 
                                       value="<?php echo esc_attr($aichat_branding_text); ?>" 
                                       class="form-control" 
                                       placeholder="<?php esc_attr_e('Your Brand Name', 'axiachat-ai'); ?>"
                                       <?php disabled(! $aichat_has_white_label); ?> />
                                <div class="form-text"><?php echo esc_html__('The name displayed after "Powered by".', 'axiachat-ai'); ?></div>
                            </div>
                            <div class="col-md-6">
                                <label for="aichat_branding_url" class="form-label">
                                    <?php echo esc_html__('Brand URL', 'axiachat-ai'); ?>
                                </label>
                                <input type="url" 
                                       id="aichat_branding_url" 
                                       name="aichat_branding_url" 
                                       value="<?php echo esc_attr($aichat_branding_url); ?>" 
                                       class="form-control" 
                                       placeholder="https://yourbrand.com"
                                       <?php disabled(! $aichat_has_white_label); ?> />
                                <div class="form-text"><?php echo esc_html__('Optional. Link the brand name to this URL.', 'axiachat-ai'); ?></div>
                            </div>
                            <div class="col-md-3">
                                <label for="aichat_branding_icon" class="form-label">
                                    <?php echo esc_html__('Brand Icon', 'axiachat-ai'); ?>
                                </label>
                                <input type="text" 
                                       id="aichat_branding_icon" 
                                       name="aichat_branding_icon" 
                                       value="<?php echo esc_attr($aichat_branding_icon); ?>" 
                                       class="form-control" 
                                       placeholder="⚡" 
                                       maxlength="4"
                                       style="width: 80px;"
                                       <?php disabled(! $aichat_has_white_label); ?> />
                                <div class="form-text"><?php echo esc_html__('Optional emoji.', 'axiachat-ai'); ?></div>
                            </div>
                        </div>
                    </fieldset>

                    <!-- Branding Preview -->
                    <div class="mt-4 p-3 bg-light rounded border">
                        <strong><?php echo esc_html__('Preview:', 'axiachat-ai'); ?></strong>
                        <div class="mt-2 p-2 bg-white border rounded text-center" style="font-size: 11px; color: #888;">
                            Powered by 
                            <span id="branding-preview-icon"><?php echo esc_html($aichat_branding_icon); ?></span>
                            <span id="branding-preview-text" style="color: #555; font-weight: 600;"><?php echo esc_html($aichat_branding_text ?: 'AxiaChat AI'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Live preview for branding fields
    $('#aichat_branding_text').on('input', function() {
        $('#branding-preview-text').text( $(this).val() || 'AxiaChat AI' );
    });
    $('#aichat_branding_icon').on('input', function() {
        $('#branding-preview-icon').text( $(this).val() );
    });
});
</script>

<?php else : ?>
<?php // Free build: show locked branding card placeholder?>
<div class="row g-4 mt-2">
    <div class="col-12">
        <div class="card shadow-sm h-100 aichat-pro-locked" style="opacity: 0.8;">
            <div class="card-header bg-secondary text-white d-flex align-items-center justify-content-between">
                <span><i class="bi bi-megaphone-fill me-2"></i><strong><?php echo esc_html__('Widget Branding', 'axiachat-ai'); ?></strong></span>
                <span class="badge bg-warning text-dark">
                    <i class="bi bi-lock-fill me-1"></i><?php echo esc_html__('Standard', 'axiachat-ai'); ?>
                </span>
            </div>
            <div class="card-body">
                <div class="alert alert-warning mb-3 d-flex align-items-center">
                    <i class="bi bi-star-fill me-2"></i>
                    <div>
                        <strong><?php echo esc_html__('Upgrade to Standard', 'axiachat-ai'); ?></strong>
                        <?php echo esc_html__('to customize or remove the "Powered by" branding.', 'axiachat-ai'); ?>
                        <?php if (function_exists('aichat_fs') && aichat_fs()) : ?>
                            <a href="<?php echo esc_url(aichat_fs()->get_upgrade_url()); ?>" class="btn btn-sm btn-warning ms-2">
                                <?php echo esc_html__('Upgrade Now', 'axiachat-ai'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="text-muted mb-0">
                    <?php echo esc_html__('Display a "Powered by" message at the bottom of the chat widget.', 'axiachat-ai'); ?>
                </p>
            </div>
        </div>
    </div>
</div>
</div>
<?php endif; ?>
