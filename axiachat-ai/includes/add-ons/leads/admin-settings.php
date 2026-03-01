<?php
/**
 * Leads Admin Settings Page
 *
 * Renders the Leads admin page with tabs for configuration and lead listing.
 * Uses Bootstrap for consistent styling with main plugin settings.
 *
 * @package AIChat
 * @subpackage Leads
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Render the main Leads admin page
 */
function aichat_leads_render_page()
{
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page navigation.
	$tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings';
	$stats = AIChat_Leads_Manager::get_stats();
	$usage = AIChat_Leads_Manager::get_usage_info();
	?>
    <div class="wrap aichat-leads-wrap">
        <h1 class="wp-heading-inline">
            <span class="dashicons dashicons-groups" style="color:#2271b1"></span>
            <?php esc_html_e('Leads', 'axiachat-ai'); ?>
        </h1>
        <p class="description mb-3"><?php esc_html_e('Manage leads captured through AI chat conversations.', 'axiachat-ai'); ?></p>
        
        <?php if (! $usage['is_pro']) : ?>
        <!-- Usage Limit Banner -->
        <div class="alert <?php echo $usage['remaining'] <= 5 ? 'alert-warning' : 'alert-info'; ?> mb-4 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <i class="bi <?php echo $usage['remaining'] <= 5 ? 'bi-exclamation-triangle-fill' : 'bi-graph-up'; ?> me-2 fs-5"></i>
                <div>
                    <strong><?php esc_html_e('Free Plan', 'axiachat-ai'); ?>:</strong>
                    <?php
					printf(
						/* translators: 1: leads used, 2: lead limit */
						esc_html__('%1$d of %2$d leads used', 'axiachat-ai'),
						intval($usage['count']),
						intval($usage['limit'])
					);
        	?>
                    <span class="text-muted ms-2">(<?php echo esc_html($usage['remaining']); ?> <?php esc_html_e('remaining', 'axiachat-ai'); ?>)</span>
                    <?php if ($usage['remaining'] <= 5 && $usage['remaining'] > 0) : ?>
                        <span class="badge bg-warning text-dark ms-2"><?php esc_html_e('Running low!', 'axiachat-ai'); ?></span>
                    <?php elseif ($usage['remaining'] === 0) : ?>
                        <span class="badge bg-danger ms-2"><?php esc_html_e('Limit reached', 'axiachat-ai'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=aichat-settings-pricing')); ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-rocket me-1"></i><?php esc_html_e('Upgrade to Pro', 'axiachat-ai'); ?>
            </a>
        </div>
        
        <!-- Progress Bar -->
        <div class="progress mb-4" style="height: 8px;">
            <div class="progress-bar <?php echo $usage['percent'] >= 80 ? 'bg-warning' : 'bg-primary'; ?> <?php echo $usage['percent'] >= 100 ? 'bg-danger' : ''; ?>" 
                 role="progressbar" 
                 style="width: <?php echo esc_attr($usage['percent']); ?>%"
                 aria-valuenow="<?php echo esc_attr($usage['count']); ?>" 
                 aria-valuemin="0" 
                 aria-valuemax="<?php echo esc_attr($usage['limit']); ?>">
            </div>
        </div>
        <?php endif; ?>
        
        <?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success message display after OAuth callback.
		if (isset($_GET['gsheets_connected']) && $_GET['gsheets_connected'] === '1') : ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?php esc_html_e('Google Sheets connected successfully!', 'axiachat-ai'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <!-- Tabs Navigation -->
        <div class="aichat-leads-tabs">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aichat-leads&tab=settings')); ?>" 
                       class="nav-link <?php echo $tab === 'settings' ? 'active' : ''; ?>">
                        <i class="bi bi-gear me-1"></i><?php esc_html_e('Settings', 'axiachat-ai'); ?>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aichat-leads&tab=list')); ?>" 
                       class="nav-link <?php echo $tab === 'list' ? 'active' : ''; ?>">
                        <i class="bi bi-list-ul me-1"></i><?php esc_html_e('Lead List', 'axiachat-ai'); ?>
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aichat-leads&tab=help')); ?>" 
                       class="nav-link <?php echo $tab === 'help' ? 'active' : ''; ?>">
                        <i class="bi bi-question-circle me-1"></i><?php esc_html_e('Help', 'axiachat-ai'); ?>
                    </a>
                </li>
            </ul>
            
            <div class="tab-content border border-top-0 bg-white p-4">
                <?php
        		if ($tab === 'settings') {
        			aichat_leads_render_settings_tab();
        		} elseif ($tab === 'help') {
        			aichat_leads_render_help_tab();
        		} else {
        			aichat_leads_render_list_tab();
        		}
	?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render Settings tab
 */
function aichat_leads_render_settings_tab()
{
	$settings = AIChat_Leads_Manager::get_settings();
	$destinations = AIChat_Leads_Manager::get_available_destinations();
	$is_pro = AIChat_Leads_Manager::has_pro_license();
	$has_integrations = AIChat_Leads_Manager::has_integrations_license();

	// Define which destinations require Standard+ (integrations)
	$pro_destinations = ['google_sheets', 'cpt', 'cf7', 'wpforms'];
	?>
    <form id="aichat-leads-settings-form">
        <?php wp_nonce_field('aichat_leads_admin', 'aichat_leads_nonce'); ?>
        
        <!-- 1. Save Destination Card (First) -->
        <div class="card shadow-sm mb-4 card100">
            <div class="card-header bg-light d-flex align-items-center">
                <i class="bi bi-diagram-3 me-2"></i>
                <strong><?php esc_html_e('Save Destination', 'axiachat-ai'); ?></strong>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3"><?php esc_html_e('Choose where to save captured leads. Select one destination.', 'axiachat-ai'); ?></p>
                
                <div class="row">
                    <?php foreach ($destinations as $dest) :
                    	$is_pro_dest = in_array($dest['id'], $pro_destinations, true);
                    	$is_locked = $is_pro_dest && ! $has_integrations;
                    	$is_disabled = ! $dest['available'] || $is_locked;
                    	?>
                        <div class="col-md-6 col-lg-3 mb-3">
                            <div class="card h-100 destination-card <?php echo $is_disabled ? 'bg-light opacity-75' : ''; ?> <?php echo $settings['destination'] === $dest['id'] ? 'border-primary' : ''; ?>" style="<?php echo $is_locked ? 'position:relative;' : ''; ?>">
                                <?php if ($is_locked) : ?>
                                    <div class="position-absolute top-0 end-0 m-2">
                                        <span class="badge bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-size: 0.7rem;">
                                            <i class="bi bi-star-fill me-1"></i>Standard+
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body">
                                    <div class="form-check">
                                        <input type="radio" class="form-check-input" 
                                               name="destination" 
                                               id="dest_<?php echo esc_attr($dest['id']); ?>"
                                               value="<?php echo esc_attr($dest['id']); ?>"
                                               <?php checked($settings['destination'], $dest['id']); ?>
                                               <?php disabled($is_disabled); ?>>
                                        <label class="form-check-label fw-semibold" for="dest_<?php echo esc_attr($dest['id']); ?>">
                                            <span class="dashicons <?php echo esc_attr($dest['icon']); ?> text-primary"></span>
                                            <?php echo esc_html($dest['name']); ?>
                                        </label>
                                    </div>
                                    <p class="small text-muted mt-2 mb-0"><?php echo esc_html($dest['description']); ?></p>
                                    <?php if (! $dest['available'] && ! $is_locked) : ?>
                                        <span class="badge bg-warning text-dark mt-2"><?php esc_html_e('Plugin not installed', 'axiachat-ai'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (! $is_pro) : ?>
                <div class="alert alert-info mt-3 mb-0 d-flex align-items-center justify-content-between">
                    <div>
                        <i class="bi bi-info-circle me-2"></i>
                        <strong><?php esc_html_e('Free Plan:', 'axiachat-ai'); ?></strong>
                        <?php esc_html_e('Internal Database only. Upgrade to Starter for unlimited leads, or Standard for Google Sheets, CPT, CF7, WPForms integrations.', 'axiachat-ai'); ?>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=axiachat-ai-pricing')); ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-rocket me-1"></i><?php esc_html_e('Upgrade', 'axiachat-ai'); ?>
                    </a>
                </div>
                <?php elseif (! $has_integrations) : ?>
                <div class="alert alert-info mt-3 mb-0 d-flex align-items-center justify-content-between">
                    <div>
                        <i class="bi bi-info-circle me-2"></i>
                        <strong><?php esc_html_e('Starter Plan:', 'axiachat-ai'); ?></strong>
                        <?php esc_html_e('Upgrade to Standard for Google Sheets, CPT, CF7, and WPForms integrations.', 'axiachat-ai'); ?>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=axiachat-ai-pricing')); ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-rocket me-1"></i><?php esc_html_e('Upgrade', 'axiachat-ai'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- CF7 Configuration -->
        <?php if (class_exists('WPCF7')) : ?>
        <div class="card shadow-sm mb-4 cf7-settings" style="<?php echo $settings['destination'] === 'cf7' ? '' : 'display:none;'; ?>">
            <div class="card-header bg-light d-flex align-items-center">
                <i class="bi bi-envelope me-2"></i>
                <strong><?php esc_html_e('Contact Form 7 Configuration', 'axiachat-ai'); ?></strong>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <label for="cf7_form_id" class="col-md-3 col-form-label"><?php esc_html_e('Select Form', 'axiachat-ai'); ?></label>
                    <div class="col-md-9">
                        <select name="cf7_form_id" id="cf7_form_id" class="form-select" style="max-width: 400px;">
                            <option value=""><?php esc_html_e('-- Select a form --', 'axiachat-ai'); ?></option>
                            <?php
                    			$cf7_forms = AIChat_Leads_Adapter_CF7::get_forms();
        	foreach ($cf7_forms as $form) :
        		?>
                                <option value="<?php echo esc_attr($form['id']); ?>" 
                                        <?php selected($settings['cf7_form_id'], $form['id']); ?>>
                                    <?php echo esc_html($form['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            <?php esc_html_e('The AI tool will automatically use the fields defined in your CF7 form.', 'axiachat-ai'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- WPForms Configuration -->
        <?php if (function_exists('wpforms')) : ?>
        <div class="card shadow-sm mb-4 wpforms-settings" style="<?php echo $settings['destination'] === 'wpforms' ? '' : 'display:none;'; ?>">
            <div class="card-header bg-light d-flex align-items-center">
                <i class="bi bi-ui-checks-grid me-2"></i>
                <strong><?php esc_html_e('WPForms Configuration', 'axiachat-ai'); ?></strong>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <label for="wpforms_form_id" class="col-md-3 col-form-label"><?php esc_html_e('Select Form', 'axiachat-ai'); ?></label>
                    <div class="col-md-9">
                        <select name="wpforms_form_id" id="wpforms_form_id" class="form-select" style="max-width: 400px;">
                            <option value=""><?php esc_html_e('-- Select a form --', 'axiachat-ai'); ?></option>
                            <?php
        		$wpforms_forms = AIChat_Leads_Adapter_WPForms::get_forms();
        	foreach ($wpforms_forms as $form) :
        		?>
                                <option value="<?php echo esc_attr($form['id']); ?>" 
                                        <?php selected($settings['wpforms_form_id'], $form['id']); ?>>
                                    <?php echo esc_html($form['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            <?php esc_html_e('The AI tool will automatically use the fields defined in your WPForms form.', 'axiachat-ai'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Google Sheets Configuration -->
        <?php
		$gsheets_connected = AIChat_Leads_GSheets_OAuth::is_connected();
	$gsheets_user_email = AIChat_Leads_GSheets_OAuth::get_user_email();
	$gsheets_spreadsheet = get_option('aichat_leads_gsheets_spreadsheet_id', '');
	$gsheets_sheet_name = get_option('aichat_leads_gsheets_sheet_name', '');
	?>
        <div class="card shadow-sm mb-4 card100 gsheets-settings" id="gsheets-settings-card" style="<?php echo $settings['destination'] === 'google_sheets' ? '' : 'display:none;'; ?>">
            <div class="card-header bg-light d-flex align-items-center">
                <i class="bi bi-file-earmark-spreadsheet me-2"></i>
                <strong><?php esc_html_e('Google Sheets Configuration', 'axiachat-ai'); ?></strong>
            </div>
            <div class="card-body">
                <?php if (! $gsheets_connected) : ?>
                    <!-- Not connected -->
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        <?php esc_html_e('Connect your Google account to save leads to a Google Spreadsheet.', 'axiachat-ai'); ?>
                    </div>
                    <a href="<?php echo esc_url(AIChat_Leads_GSheets_OAuth::get_auth_url()); ?>" class="btn btn-primary">
                        <i class="bi bi-google me-2"></i><?php esc_html_e('Connect Google Sheets', 'axiachat-ai'); ?>
                    </a>
                <?php else : ?>
                    <!-- Connected -->
                    <div class="alert alert-success mb-3">
                        <i class="bi bi-check-circle me-2"></i>
                        <?php
					if (! empty($gsheets_user_email)) {
						printf(
							/* translators: %s: Google account email */
							esc_html__('Connected as: %s', 'axiachat-ai'),
							'<strong>' . esc_html($gsheets_user_email) . '</strong>'
						);
					} else {
						esc_html_e('Connected to Google Sheets', 'axiachat-ai');
					}
                	?>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-3" id="gsheets-disconnect">
                            <i class="bi bi-x-circle me-1"></i><?php esc_html_e('Disconnect', 'axiachat-ai'); ?>
                        </button>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="gsheets_spreadsheet_id" class="col-md-3 col-form-label">
                            <?php esc_html_e('Google Sheet URL', 'axiachat-ai'); ?>
                            <span class="text-danger">*</span>
                        </label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" name="gsheets_spreadsheet_id" id="gsheets_spreadsheet_id"
                                   value="<?php echo esc_attr($gsheets_spreadsheet); ?>" 
                                   placeholder="https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit"
                                   style="max-width: 600px;">
                            <small class="text-muted d-block mt-1">
                                <?php esc_html_e('Paste the full URL from your Google Sheets share link or browser address bar.', 'axiachat-ai'); ?>
                            </small>
                            <div class="d-flex align-items-center mt-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="gsheets-test-connection">
                                    <i class="bi bi-plug me-1"></i><?php esc_html_e('Test Connection', 'axiachat-ai'); ?>
                                </button>
                                <span id="gsheets-test-result" class="ms-2"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="gsheets_sheet_name" class="col-md-3 col-form-label">
                            <?php esc_html_e('Sheet Name', 'axiachat-ai'); ?>
                            <span class="text-muted small">(<?php esc_html_e('optional', 'axiachat-ai'); ?>)</span>
                        </label>
                        <div class="col-md-9">
                            <input type="text" class="form-control" name="gsheets_sheet_name" id="gsheets_sheet_name"
                                   value="<?php echo esc_attr($gsheets_sheet_name); ?>" 
                                   placeholder=""
                                   style="max-width: 300px;">
                            <small class="text-muted"><?php esc_html_e('Leave empty to use the first sheet automatically. Only specify if you want a specific tab.', 'axiachat-ai'); ?></small>
                        </div>
                    </div>
                    
                    <div class="alert alert-light border mt-3">
                        <strong><i class="bi bi-lightbulb me-1"></i><?php esc_html_e('How it works:', 'axiachat-ai'); ?></strong>
                        <ol class="mb-0 mt-2 small">
                            <li><?php esc_html_e('Create a new spreadsheet in Google Sheets', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Copy the URL from your browser and paste it above', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Headers will be added automatically on the first lead', 'axiachat-ai'); ?></li>
                            <li><?php esc_html_e('Each new lead will be added as a new row', 'axiachat-ai'); ?></li>
                        </ol>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 2. Notifications Card -->
        <div class="card shadow-sm mb-4 card100">
            <div class="card-header bg-light d-flex align-items-center">
                <i class="bi bi-bell me-2"></i>
                <strong><?php esc_html_e('Notifications', 'axiachat-ai'); ?></strong>
            </div>
            <div class="card-body">
                <div class="row mb-3 pb-3 border-bottom">
                    <div class="col-md-8">
                        <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
                            <input type="checkbox" name="notify_enabled" id="notify_enabled" value="1" <?php checked($settings['notify_enabled']); ?>>
                            <strong><?php esc_html_e('Send email notification when a new lead is captured', 'axiachat-ai'); ?></strong>
                        </label>
                    </div>
                    <div class="col-md-4 text-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#leadEmailTemplateModal">
                            <i class="bi bi-pencil me-1"></i><?php esc_html_e('Edit Template', 'axiachat-ai'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="row">
                    <label for="notify_email" class="col-md-3 col-form-label"><?php esc_html_e('Notification Email', 'axiachat-ai'); ?></label>
                    <div class="col-md-9">
                        <input type="email" class="form-control" name="notify_email" id="notify_email"
                               value="<?php echo esc_attr($settings['notify_email']); ?>" 
                               placeholder="<?php echo esc_attr(get_option('admin_email')); ?>"
                               style="max-width: 400px;">
                        <small class="text-muted"><?php esc_html_e('Leave empty to use the site admin email.', 'axiachat-ai'); ?></small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 3. Other Settings Card (Last) -->
        <div class="card shadow-sm mb-4 card100">
            <div class="card-header bg-light d-flex align-items-center">
                <i class="bi bi-sliders me-2"></i>
                <strong><?php esc_html_e('Other Settings', 'axiachat-ai'); ?></strong>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <label class="col-md-3 col-form-label"><?php esc_html_e('Privacy', 'axiachat-ai'); ?></label>
                    <div class="col-md-9">
                        <div class="form-check mt-2">
                            <input type="checkbox" name="store_ip" id="store_ip" value="1" <?php checked($settings['store_ip']); ?>>
                            <label class="form-check-label" for="store_ip">
                                <?php esc_html_e('Store IP hash (for GDPR compliance, only hash is stored)', 'axiachat-ai'); ?>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <label for="retention_days" class="col-md-3 col-form-label"><?php esc_html_e('Data Retention', 'axiachat-ai'); ?></label>
                    <div class="col-md-9">
                        <div class="input-group" style="max-width: 200px;">
                            <input type="number" class="form-control" name="retention_days" id="retention_days"
                                   value="<?php echo esc_attr($settings['retention_days']); ?>" min="0" max="365">
                            <span class="input-group-text"><?php esc_html_e('days', 'axiachat-ai'); ?></span>
                        </div>
                        <small class="text-muted"><?php esc_html_e('Automatically delete leads older than this many days. Set to 0 to keep forever.', 'axiachat-ai'); ?></small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 4. Webhook Card -->
        <?php $webhook_url = get_option('aichat_leads_webhook_url', ''); ?>
        <div class="card shadow-sm mb-4 card100 <?php echo ! $is_pro ? 'border-secondary' : ''; ?>" style="<?php echo ! $is_pro ? 'position: relative;' : ''; ?>">
            <?php if (! $is_pro) : ?>
                <div class="position-absolute top-0 end-0 m-2" style="z-index: 10;">
                    <span class="badge bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="bi bi-star-fill me-1"></i>PRO
                    </span>
                </div>
            <?php endif; ?>
            <div class="card-header bg-light d-flex align-items-center">
                <i class="bi bi-broadcast me-2"></i>
                <strong><?php esc_html_e('Webhook Integration', 'axiachat-ai'); ?></strong>
                <span class="badge bg-secondary ms-2"><?php esc_html_e('Optional', 'axiachat-ai'); ?></span>
            </div>
            <div class="card-body <?php echo ! $is_pro ? 'opacity-75' : ''; ?>">
                <p class="text-muted small mb-3">
                    <?php esc_html_e('Send lead data to an external service via webhook. This works with any destination and is useful for integrations with automation tools like n8n, Zapier, Make, or custom APIs.', 'axiachat-ai'); ?>
                </p>
                
                <div class="row mb-3">
                    <label for="webhook_url" class="col-md-3 col-form-label"><?php esc_html_e('Webhook URL', 'axiachat-ai'); ?></label>
                    <div class="col-md-9">
                        <input type="url" class="form-control" name="webhook_url" id="webhook_url"
                               value="<?php echo esc_attr($webhook_url); ?>" 
                               placeholder="https://your-n8n-instance.com/webhook/abc123"
                               style="max-width: 500px;"
                               <?php disabled(! $is_pro); ?>>
                        <small class="text-muted d-block mt-1">
                            <?php if ($is_pro) : ?>
                                <?php esc_html_e('Leave empty to disable. A POST request with JSON data will be sent for each new lead.', 'axiachat-ai'); ?>
                            <?php else : ?>
                                <i class="bi bi-lock me-1"></i><?php esc_html_e('Webhooks are a PRO feature. Upgrade to enable automation integrations.', 'axiachat-ai'); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
                
                <div class="alert alert-light border small mb-0">
                    <strong><i class="bi bi-code-slash me-1"></i><?php esc_html_e('JSON Payload Format:', 'axiachat-ai'); ?></strong>
                    <pre class="mb-0 mt-2" style="font-size: 12px;"><code>{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+1234567890",
  "company": "ACME Inc",
  "interest": "Product inquiry",
  "notes": "Customer message...",
  "bot": "sales-bot",
  "date": "2026-01-26 14:30:00",
  "source": "axiachat"
}</code></pre>
                </div>
            </div>
        </div>
        
        <!-- Save Button -->
        <div class="card shadow-sm">
            <div class="card-body">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i><?php esc_html_e('Save Settings', 'axiachat-ai'); ?>
                </button>
            </div>
        </div>
    </form>
    
    <!-- Lead Email Template Modal -->
    <div class="modal fade" id="leadEmailTemplateModal" tabindex="-1" aria-labelledby="leadEmailTemplateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="leadEmailTemplateModalLabel">
                        <i class="bi bi-envelope me-2"></i><?php esc_html_e('Lead Notification Email Template', 'axiachat-ai'); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="lead_email_subject" class="form-label"><?php esc_html_e('Email Subject', 'axiachat-ai'); ?></label>
                        <input type="text" class="form-control" id="lead_email_subject" name="lead_email_subject"
                               value="<?php echo esc_attr($settings['email_subject'] ?? __('New Lead Captured: {name}', 'axiachat-ai')); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="lead_email_body" class="form-label"><?php esc_html_e('Email Body', 'axiachat-ai'); ?></label>
                        <textarea class="form-control" id="lead_email_body" name="lead_email_body" rows="12"><?php
                		echo esc_textarea($settings['email_body'] ?? __("A new lead has been captured:\n\nName: {name}\nEmail: {email}\nPhone: {phone}\nCompany: {company}\n\nMessage:\n{message}\n\nDate: {date}\nBot: {bot}\n\n---\nView all leads: {admin_url}", 'axiachat-ai'));
	?></textarea>
                    </div>
                    <div class="alert alert-info small">
                        <strong><?php esc_html_e('Available placeholders:', 'axiachat-ai'); ?></strong><br>
                        <code>{name}</code>, <code>{email}</code>, <code>{phone}</code>, <code>{company}</code>, <code>{message}</code>, <code>{date}</code>, <code>{bot}</code>, <code>{admin_url}</code>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php esc_html_e('Close', 'axiachat-ai'); ?></button>
                    <button type="button" class="btn btn-primary" id="save-lead-email-template"><?php esc_html_e('Save Template', 'axiachat-ai'); ?></button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render List tab
 */
function aichat_leads_render_list_tab()
{
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page filters and pagination.
	$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$estado = isset($_GET['estado']) ? sanitize_key($_GET['estado']) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

	$result = AIChat_Leads_Manager::get_leads([
		'page' => $page,
		'estado' => $estado,
		'search' => $search,
	]);

	$leads = $result['leads'];
	$total_pages = $result['total_pages'];
	?>
    
    <!-- Toolbar -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <form method="get" class="d-flex flex-wrap gap-2 align-items-center">
            <input type="hidden" name="page" value="aichat-leads">
            <input type="hidden" name="tab" value="list">
            <div class="input-group" style="width: auto;">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" class="form-control" name="s" value="<?php echo esc_attr($search); ?>" 
                       placeholder="<?php esc_attr_e('Search leads...', 'axiachat-ai'); ?>" style="width: 200px;">
            </div>
            <select name="estado" class="form-select" style="width: auto;">
                <option value=""><?php esc_html_e('All statuses', 'axiachat-ai'); ?></option>
                <option value="nuevo" <?php selected($estado, 'nuevo'); ?>><?php esc_html_e('New', 'axiachat-ai'); ?></option>
                <option value="contactado" <?php selected($estado, 'contactado'); ?>><?php esc_html_e('Contacted', 'axiachat-ai'); ?></option>
                <option value="convertido" <?php selected($estado, 'convertido'); ?>><?php esc_html_e('Converted', 'axiachat-ai'); ?></option>
                <option value="descartado" <?php selected($estado, 'descartado'); ?>><?php esc_html_e('Discarded', 'axiachat-ai'); ?></option>
            </select>
            <button type="submit" class="btn btn-secondary">
                <i class="bi bi-funnel me-1"></i><?php esc_html_e('Filter', 'axiachat-ai'); ?>
            </button>
        </form>
        <button type="button" class="btn btn-outline-primary" id="export-leads-btn">
            <i class="bi bi-download me-1"></i><?php esc_html_e('Export CSV', 'axiachat-ai'); ?>
        </button>
    </div>
    
    <!-- Leads Table -->
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 40px;">
                        <input type="checkbox" class="form-check-input" id="cb-select-all">
                    </th>
                    <th><?php esc_html_e('Name', 'axiachat-ai'); ?></th>
                    <th><?php esc_html_e('Email', 'axiachat-ai'); ?></th>
                    <th><?php esc_html_e('Phone', 'axiachat-ai'); ?></th>
                    <th><?php esc_html_e('Bot', 'axiachat-ai'); ?></th>
                    <th><?php esc_html_e('Status', 'axiachat-ai'); ?></th>
                    <th><?php esc_html_e('Date', 'axiachat-ai'); ?></th>
                    <th style="width: 120px;"><?php esc_html_e('Actions', 'axiachat-ai'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leads)) : ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-inbox display-6 d-block mb-2"></i>
                            <?php esc_html_e('No leads found.', 'axiachat-ai'); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($leads as $lead) : ?>
                        <tr data-id="<?php echo esc_attr($lead['id']); ?>">
                            <td>
                                <input type="checkbox" class="form-check-input" name="leads[]" value="<?php echo esc_attr($lead['id']); ?>">
                            </td>
                            <td>
                                <strong><?php echo esc_html($lead['nombre'] ?: '—'); ?></strong>
                                <?php if (! empty($lead['empresa'])) : ?>
                                    <br><small class="text-muted"><?php echo esc_html($lead['empresa']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (! empty($lead['email'])) : ?>
                                    <a href="mailto:<?php echo esc_attr($lead['email']); ?>" class="text-decoration-none">
                                        <i class="bi bi-envelope me-1"></i><?php echo esc_html($lead['email']); ?>
                                    </a>
                                <?php else : ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (! empty($lead['telefono'])) : ?>
                                    <a href="tel:<?php echo esc_attr($lead['telefono']); ?>" class="text-decoration-none">
                                        <i class="bi bi-telephone me-1"></i><?php echo esc_html($lead['telefono']); ?>
                                    </a>
                                <?php else : ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code class="bg-light px-2 py-1 rounded small"><?php echo esc_html($lead['bot_slug'] ?: '—'); ?></code>
                            </td>
                            <td>
                                <select class="form-select form-select-sm lead-status-select" data-id="<?php echo esc_attr($lead['id']); ?>" style="width: auto;">
                                    <option value="nuevo" <?php selected($lead['estado'], 'nuevo'); ?>><?php esc_html_e('New', 'axiachat-ai'); ?></option>
                                    <option value="contactado" <?php selected($lead['estado'], 'contactado'); ?>><?php esc_html_e('Contacted', 'axiachat-ai'); ?></option>
                                    <option value="convertido" <?php selected($lead['estado'], 'convertido'); ?>><?php esc_html_e('Converted', 'axiachat-ai'); ?></option>
                                    <option value="descartado" <?php selected($lead['estado'], 'descartado'); ?>><?php esc_html_e('Discarded', 'axiachat-ai'); ?></option>
                                </select>
                            </td>
                            <td>
                                <small><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($lead['created_at']))); ?></small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary view-lead-btn" data-id="<?php echo esc_attr($lead['id']); ?>" title="<?php esc_attr_e('View', 'axiachat-ai'); ?>">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if (! empty($lead['conversation_id'])) : ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=aichat-logs-detail&id=' . $lead['conversation_id'])); ?>" 
                                           class="btn btn-outline-info" title="<?php esc_attr_e('View Conversation', 'axiachat-ai'); ?>">
                                            <i class="bi bi-chat-dots"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-outline-danger delete-lead-btn" data-id="<?php echo esc_attr($lead['id']); ?>" title="<?php esc_attr_e('Delete', 'axiachat-ai'); ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1) : ?>
        <nav aria-label="Leads pagination" class="mt-3">
            <ul class="pagination justify-content-center">
                <?php
				for ($i = 1; $i <= $total_pages; $i++) :
					$url = add_query_arg('paged', $i);
					$active = ($i === $page) ? 'active' : '';
					?>
                    <li class="page-item <?php echo esc_attr($active); ?>">
                        <a class="page-link" href="<?php echo esc_url($url); ?>"><?php echo esc_html($i); ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
    
    <!-- Lead Detail Modal (Bootstrap 5) -->
    <div class="modal fade" id="lead-detail-modal" tabindex="-1" aria-labelledby="leadDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="leadDetailModalLabel">
                        <i class="bi bi-person-badge me-2"></i><?php esc_html_e('Lead Details', 'axiachat-ai'); ?>
                    </h5>
                    <button type="button" class="btn-close close-modal" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="lead-detail-content">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render Help tab
 */
function aichat_leads_render_help_tab()
{
	?>
    <div class="row">
        <div class="col-lg-8">
            
            <!-- Overview Card -->
            <div class="card shadow-sm mb-4 card100">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Lead Capture Overview</strong>
                </div>
                <div class="card-body">
                    <p>The Lead Capture add-on enables your AI chatbot to collect and save customer contact information during conversations. When users provide their details (name, email, phone, etc.), the bot can automatically save this data to your chosen destination.</p>
                    
                    <h6 class="fw-bold mt-4"><i class="bi bi-diagram-3 me-2"></i>Available Destinations</h6>
                    <ul class="mb-0">
                        <li><strong>Internal Database:</strong> Saves leads to the plugin's built-in table. View and manage them from the Lead List tab.</li>
                        <li><strong>Google Sheets:</strong> Saves leads directly to a Google Spreadsheet. Connect your Google account and paste the sheet URL.</li>
                        <li><strong>WordPress CPT:</strong> Creates a custom post type entry for each lead, enabling native WP integration.</li>
                        <li><strong>Contact Form 7:</strong> Submits the lead data through your CF7 form, triggering its notifications and integrations.</li>
                        <li><strong>WPForms:</strong> Submits leads through WPForms, creating entries (Pro) or triggering notifications (Lite).</li>
                    </ul>
                </div>
            </div>
            
            <!-- How It Works Card -->
            <div class="card shadow-sm mb-4 card100">
                <div class="card-header bg-light">
                    <i class="bi bi-gear-wide-connected me-2"></i>
                    <strong>How It Works</strong>
                </div>
                <div class="card-body">
                    <ol>
                        <li class="mb-2"><strong>Enable the Tool:</strong> Go to your Bot settings and enable the <code>save_lead</code> tool in the AI Tools section.</li>
                        <li class="mb-2"><strong>Configure Destination:</strong> Choose where leads should be saved in the Settings tab.</li>
                        <li class="mb-2"><strong>Add Instructions:</strong> Include lead capture instructions in your bot's System Instructions (see examples below).</li>
                        <li class="mb-2"><strong>Automatic Schema:</strong> The tool automatically adapts its parameters based on your destination:
                            <ul>
                                <li>For Internal/CPT: Uses standard fields (name, email, phone, company, interest, notes)</li>
                                <li>For CF7/WPForms: Dynamically uses the actual form fields from your selected form</li>
                            </ul>
                        </li>
                    </ol>
                </div>
            </div>
            
            <!-- System Instructions Examples -->
            <div class="card shadow-sm mb-4 card100">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-code-slash me-2"></i>
                    <strong>System Instructions Examples</strong>
                </div>
                <div class="card-body">
                    <p>Add these instructions to your bot's <strong>System Instructions</strong> field to enable intelligent lead capture:</p>
                    
                    <h6 class="fw-bold mt-4"><i class="bi bi-building me-2"></i>Example 1: Business/Sales Bot</h6>
                    <div class="bg-light p-3 rounded mb-4">
                        <pre class="mb-0" style="white-space: pre-wrap; font-size: 13px;"><code>## Lead Capture Instructions

You have the ability to save customer contact information using the save_lead tool. Follow these guidelines:

1. **When to capture leads:**
   - When a user expresses interest in our products/services
   - When a user requests a quote, demo, or callback
   - When a user asks to be contacted by sales

2. **Information to collect:**
   - Name (required)
   - Email or phone (at least one required)
   - Company name (if B2B)
   - What they're interested in
   - Any relevant notes from the conversation

3. **How to ask:**
   - Be natural and conversational
   - Explain why you need the information
   - Ask for confirmation before saving: "Would you like me to save your contact details so our team can reach out?"

4. **After saving:**
   - Confirm the lead was saved successfully
   - Let them know someone will contact them soon
   - Ask if there's anything else you can help with</code></pre>
                    </div>
                    
                    <h6 class="fw-bold mt-4"><i class="bi bi-headset me-2"></i>Example 2: Support/Contact Bot</h6>
                    <div class="bg-light p-3 rounded mb-4">
                        <pre class="mb-0" style="white-space: pre-wrap; font-size: 13px;"><code>## Contact Form Assistant

You help users submit contact requests. When a user wants to get in touch or leave a message:

1. Collect their information naturally through conversation:
   - Full name
   - Email address
   - Phone number (optional)
   - Their message or inquiry

2. Before saving, summarize what you collected:
   "Let me confirm: Your name is [name], email is [email], and you'd like to ask about [topic]. Should I submit this?"

3. Use the save_lead tool only after user confirmation.

4. After successful submission, thank them and provide expected response time.</code></pre>
                    </div>
                    
                    <h6 class="fw-bold mt-4"><i class="bi bi-calendar-check me-2"></i>Example 3: Appointment/Demo Request</h6>
                    <div class="bg-light p-3 rounded mb-4">
                        <pre class="mb-0" style="white-space: pre-wrap; font-size: 13px;"><code>## Demo Request Handler

When users want to schedule a demo or consultation:

1. Express enthusiasm: "Great! I'd be happy to help you schedule a demo."

2. Collect required information:
   - Name
   - Business email
   - Company name
   - Preferred contact method
   - Best time to call (store in notes)

3. Validate email format before saving.

4. Always ask: "May I save your details so our team can schedule the demo?"

5. Use save_lead with:
   - name: User's full name
   - email: Business email
   - company: Company name
   - interest: "Demo Request"
   - notes: Preferred time and any other relevant details

6. Confirm: "Your demo request has been submitted. You'll hear from us within 24 hours."</code></pre>
                    </div>
                </div>
            </div>
            
            <!-- Best Practices Card -->
            <div class="card shadow-sm mb-4 card100">
                <div class="card-header bg-warning text-dark">
                    <i class="bi bi-lightbulb me-2"></i>
                    <strong>Best Practices</strong>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold text-success"><i class="bi bi-check-circle me-1"></i> Do</h6>
                            <ul>
                                <li>Always ask for user consent before saving</li>
                                <li>Explain why you need their information</li>
                                <li>Confirm the data before submitting</li>
                                <li>Provide clear next steps after capture</li>
                                <li>Be transparent about how data will be used</li>
                                <li>Respect user privacy and GDPR requirements</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold text-danger"><i class="bi bi-x-circle me-1"></i> Don't</h6>
                            <ul>
                                <li>Force users to provide information</li>
                                <li>Save data without explicit consent</li>
                                <li>Ask for unnecessary personal details</li>
                                <li>Make promises about response times you can't keep</li>
                                <li>Store sensitive data (passwords, credit cards)</li>
                                <li>Capture leads from users who just want information</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            
            <!-- Quick Reference Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-bookmark me-2"></i>
                    <strong>Quick Reference</strong>
                </div>
                <div class="card-body">
                    <h6 class="fw-bold">Tool Name</h6>
                    <p><code>save_lead</code></p>
                    
                    <h6 class="fw-bold mt-3">Default Parameters</h6>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Field</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><code>nombre</code></td><td>string</td></tr>
                            <tr><td><code>email</code></td><td>string</td></tr>
                            <tr><td><code>telefono</code></td><td>string</td></tr>
                            <tr><td><code>empresa</code></td><td>string</td></tr>
                            <tr><td><code>interes</code></td><td>string</td></tr>
                            <tr><td><code>notas</code></td><td>string</td></tr>
                        </tbody>
                    </table>
                    <small class="text-muted">For CF7/WPForms, parameters are dynamically generated from your form fields.</small>
                </div>
            </div>
            
            <!-- Troubleshooting Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-secondary text-white">
                    <i class="bi bi-wrench me-2"></i>
                    <strong>Troubleshooting</strong>
                </div>
                <div class="card-body">
                    <h6 class="fw-bold">Lead not saving?</h6>
                    <ul class="small">
                        <li>Check that the <code>save_lead</code> tool is enabled in Bot settings</li>
                        <li>Verify destination is properly configured</li>
                        <li>For CF7/WPForms: ensure a form is selected</li>
                        <li>Check the debug log for error details</li>
                    </ul>
                    
                    <h6 class="fw-bold mt-3">Bot not asking for contact info?</h6>
                    <ul class="small mb-0">
                        <li>Add lead capture instructions to System Instructions</li>
                        <li>Be specific about when to collect data</li>
                        <li>Test with explicit requests like "I want to be contacted"</li>
                    </ul>
                </div>
            </div>
            
            <!-- Plan Limits Card -->
            <div class="card shadow-sm mb-4 border-primary">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-speedometer2 me-2"></i>
                    <strong>Plan Limits</strong>
                </div>
                <div class="card-body small">
                    <h6 class="fw-bold">Free Plan</h6>
                    <ul class="mb-3">
                        <li><strong>25 leads</strong> total (all destinations combined)</li>
                        <li>All features included</li>
                        <li>Internal, CPT, CF7, WPForms destinations</li>
                    </ul>
                    
                    <h6 class="fw-bold">Pro Plan</h6>
                    <ul class="mb-0">
                        <li><strong>Unlimited leads</strong></li>
                        <li>Priority support</li>
                        <li>Future features included</li>
                    </ul>
                    
                    <a href="<?php echo esc_url(admin_url('admin.php?page=aichat-settings-pricing')); ?>" class="btn btn-outline-primary btn-sm w-100 mt-3">
                        <i class="bi bi-rocket me-1"></i>Upgrade to Pro
                    </a>
                </div>
            </div>
            
            <!-- GDPR Note Card -->
            <div class="card shadow-sm border-warning">
                <div class="card-header bg-warning text-dark">
                    <i class="bi bi-shield-check me-2"></i>
                    <strong>GDPR & Privacy</strong>
                </div>
                <div class="card-body small">
                    <p class="mb-2">When capturing leads, ensure compliance with data protection regulations:</p>
                    <ul class="mb-0">
                        <li>Obtain explicit consent before saving data</li>
                        <li>Configure data retention policies in Settings</li>
                        <li>Enable IP hashing instead of storing raw IPs</li>
                        <li>Include privacy policy links in your chatbot</li>
                        <li>Allow users to request data deletion</li>
                    </ul>
                </div>
            </div>
            
        </div>
    </div>
    <?php
}
