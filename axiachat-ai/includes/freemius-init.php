<?php

/**
 * Freemius Integration
 *
 * This file initializes Freemius SDK for premium features.
 * Replace the placeholder with actual Freemius SDK when ready.
 *
 * To integrate Freemius:
 * 1. Create account at https://freemius.com
 * 2. Add your plugin
 * 3. Download SDK and place in /freemius folder
 * 4. Replace the code below with the generated snippet
 *
 * @package AIChat
 * @since 2.2.0
 */
if (!defined('ABSPATH')) {
	exit;
}
/**
 * Initialize Freemius
 *
 * IMPORTANT: Replace this entire function with the code from Freemius dashboard
 * after you create your product there.
 */
if (!function_exists('aichat_fs')) {
	/**
	 * Freemius instance getter
	 *
	 * @return Freemius|null
	 */
	function aichat_fs()
	{
		global $aichat_fs;
		if (!isset($aichat_fs)) {
			// Check if Freemius SDK exists
			$freemius_path = AICHAT_PLUGIN_DIR . 'vendor/freemius/start.php';
			if (!file_exists($freemius_path)) {
				// SDK not installed yet - return null
				return null;
			}
			// Include Freemius SDK
			require_once $freemius_path;
			// Initialize Freemius
			// REPLACE THIS WITH YOUR FREEMIUS INIT CODE FROM DASHBOARD
			$aichat_fs = fs_dynamic_init([
				'id' => '23266',
				'slug' => 'axiachat-ai',
				'type' => 'plugin',
				'public_key' => 'pk_a47fc9c694b6ba7bc2b2d17313a1d',
				'is_premium' => false,
				'has_addons' => false,
				'has_paid_plans' => true,
				'trial' => [
					'days' => 7,
					'is_require_payment' => false,
				],
				'menu' => [
					'slug' => 'aichat-settings',
					'support' => false,
					'pricing' => false,
					'parent' => [
						'slug' => 'aichat-settings',
					],
				],
				'is_live' => true,
			]);
		}

		return $aichat_fs;
	}

	// Initialize Freemius
	$aichat_fs_instance = aichat_fs();
	// Signal that SDK was initiated
	if ($aichat_fs_instance) {
		do_action('aichat_fs_loaded');
	}
}
/**
 * Freemius Plan IDs
 */
define('AICHAT_PLAN_FREE', 39008);
define('AICHAT_PLAN_STARTER', 39318);
define('AICHAT_PLAN_STANDARD', 39332);
define('AICHAT_PLAN_PROFESSIONAL', 39013);
/**
 * Freemius Feature IDs
 */
define('AICHAT_FEATURE_UNLIMITED_BOTS', 26987);
define('AICHAT_FEATURE_UNLIMITED_MCP', 26988);
define('AICHAT_FEATURE_UNLIMITED_WEBHOOKS', 26989);
define('AICHAT_FEATURE_UNLIMITED_LEADS', 26990);
define('AICHAT_FEATURE_UNLIMITED_APPOINTMENTS', 26991);
define('AICHAT_FEATURE_JS_EMBED', 26992);
define('AICHAT_FEATURE_LEADS_INTEGRATIONS', 26993);
define('AICHAT_FEATURE_APPOINTMENTS_INTEGRATIONS', 26994);
define('AICHAT_FEATURE_LIVECHAT', 26995);
define('AICHAT_FEATURE_WHITE_LABEL', 27024);
define('AICHAT_FEATURE_TWO_BOTS', 27769);
/**
 * Helper function to check if Pro license is active (any paid plan)
 * Works with or without Freemius SDK
 *
 * @return bool
 */
function aichat_is_pro()
{
	// Check Freemius
	if (function_exists('aichat_fs')) {
		$fs = aichat_fs();
		if ($fs && ($fs->is_paying() || $fs->is_trial())) {
			return true;
		}
	}
	// Fallback: check local license
	$license = get_option('aichat_pro_license_key', '');
	if (!empty($license) && aichat_validate_local_license($license)) {
		return true;
	}

	return apply_filters('aichat_is_pro', false);
}

/**
 * Check if user is on a specific plan or higher
 *
 * @param string $plan_name Plan name: 'starter', 'standard', 'professional'
 * @return bool
 */
function aichat_is_plan($plan_name)
{
	$fs = (function_exists('aichat_fs') ? aichat_fs() : null);
	if (!$fs) {
		return apply_filters('aichat_is_plan_' . $plan_name, false);
	}
	if (!$fs->is_paying() && !$fs->is_trial()) {
		return false;
	}
	// Plan hierarchy: starter < standard < professional
	$hierarchy = [
		'starter' => 1,
		'standard' => 2,
		'professional' => 3,
	];
	$required_level = (isset($hierarchy[$plan_name]) ? $hierarchy[$plan_name] : 0);
	if ($fs->is_plan('professional', true)) {
		return $required_level <= 3;
	}
	if ($fs->is_plan('standard', true)) {
		return $required_level <= 2;
	}
	if ($fs->is_plan('starter', true)) {
		return $required_level <= 1;
	}

	return false;
}

/**
 * Check if user has a specific feature (by Feature ID)
 * This is the most reliable way to check feature availability
 *
 * @param int $feature_id Freemius Feature ID
 * @return bool
 */
function aichat_has_feature($feature_id)
{
	$fs = (function_exists('aichat_fs') ? aichat_fs() : null);
	if (!$fs) {
		return apply_filters('aichat_has_feature_' . $feature_id, false);
	}
	// Free users have no paid features
	if (!$fs->is_paying() && !$fs->is_trial()) {
		return false;
	}
	// Map feature IDs to minimum plan required
	$feature_to_plan = [
		AICHAT_FEATURE_UNLIMITED_BOTS => 'standard',
		AICHAT_FEATURE_TWO_BOTS => 'starter',
		AICHAT_FEATURE_UNLIMITED_MCP => 'starter',
		AICHAT_FEATURE_UNLIMITED_WEBHOOKS => 'starter',
		AICHAT_FEATURE_UNLIMITED_LEADS => 'starter',
		AICHAT_FEATURE_UNLIMITED_APPOINTMENTS => 'starter',
		AICHAT_FEATURE_JS_EMBED => 'starter',
		AICHAT_FEATURE_LEADS_INTEGRATIONS => 'standard',
		AICHAT_FEATURE_APPOINTMENTS_INTEGRATIONS => 'standard',
		AICHAT_FEATURE_WHITE_LABEL => 'standard',
		AICHAT_FEATURE_LIVECHAT => 'professional',
	];
	if (!isset($feature_to_plan[$feature_id])) {
		return false;
	}

	return aichat_is_plan($feature_to_plan[$feature_id]);
}

/**
 * Get maximum allowed bots for current plan
 * Free: 1, Starter: 2, Standard+: unlimited
 *
 * @return int
 */
function aichat_max_bots_allowed()
{
	// Standard and Professional have unlimited bots
	if (aichat_has_feature(AICHAT_FEATURE_UNLIMITED_BOTS)) {
		return PHP_INT_MAX;
	}
	// Starter plan has 2 bots (feature ID 27769)
	if (aichat_has_feature(AICHAT_FEATURE_TWO_BOTS)) {
		return apply_filters('aichat_starter_max_bots', 2);
	}

	// Free plan has 1 bot
	return apply_filters('aichat_free_max_bots', 1);
}

/**
 * Get maximum allowed MCP servers for current plan
 * Free: 1, Paid: unlimited
 *
 * @return int
 */
function aichat_max_mcp_servers_allowed()
{
	if (aichat_has_feature(AICHAT_FEATURE_UNLIMITED_MCP)) {
		return PHP_INT_MAX;
	}

	return apply_filters('aichat_free_max_mcp_servers', 1);
}

/**
 * Get maximum allowed webhooks for current plan
 * Free: 1, Paid: unlimited
 *
 * @return int
 */
function aichat_max_webhooks_allowed()
{
	if (aichat_has_feature(AICHAT_FEATURE_UNLIMITED_WEBHOOKS)) {
		return PHP_INT_MAX;
	}

	return apply_filters('aichat_free_max_webhooks', 1);
}

/**
 * Get maximum allowed leads for current plan
 * Free: 50, Paid: unlimited
 *
 * @return int
 */
function aichat_max_leads_allowed()
{
	if (aichat_has_feature(AICHAT_FEATURE_UNLIMITED_LEADS)) {
		return PHP_INT_MAX;
	}

	return apply_filters('aichat_free_max_leads', 50);
}

/**
 * Get maximum allowed appointments for current plan
 * Free: 50, Paid: unlimited
 *
 * @return int
 */
function aichat_max_appointments_allowed()
{
	if (aichat_has_feature(AICHAT_FEATURE_UNLIMITED_APPOINTMENTS)) {
		return PHP_INT_MAX;
	}

	return apply_filters('aichat_free_max_appointments', 50);
}

/**
 * Check if JS Embed (external websites) is allowed
 * Only for paid plans (Starter+)
 *
 * @return bool
 */
function aichat_can_use_js_embed()
{
	return aichat_has_feature(AICHAT_FEATURE_JS_EMBED);
}

/**
 * Check if Leads integrations are allowed (Google Sheets, CF7, WPForms)
 * Only for Standard+ plans
 *
 * @return bool
 */
function aichat_can_use_leads_integrations()
{
	return aichat_has_feature(AICHAT_FEATURE_LEADS_INTEGRATIONS);
}

/**
 * Check if Appointments integrations are allowed (Bookly, Amelia, SSA, Google Cal)
 * Only for Standard+ plans
 *
 * @return bool
 */
function aichat_can_use_appointments_integrations()
{
	return aichat_has_feature(AICHAT_FEATURE_APPOINTMENTS_INTEGRATIONS);
}

/**
 * Check if LiveChat is allowed
 * Only for Professional plan
 *
 * @return bool
 */
function aichat_can_use_livechat()
{
	return aichat_has_feature(AICHAT_FEATURE_LIVECHAT);
}

/**
 * Validate local license key (simple validation)
 * For production, you'd want server-side validation
 *
 * @param string $license License key
 * @return bool
 */
function aichat_validate_local_license($license)
{
	// Simple format check - replace with real validation
	if (strlen($license) < 20) {
		return false;
	}
	// Check cached validation result
	$cached = get_transient('aichat_license_valid');
	if ($cached !== false) {
		return $cached === 'valid';
	}
	// For now, just check if it looks like a valid format
	// In production, validate against your licensing server
	$valid = preg_match('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $license);
	// Cache for 24 hours
	set_transient('aichat_license_valid', ($valid ? 'valid' : 'invalid'), DAY_IN_SECONDS);

	return $valid;
}

/**
 * Get upgrade URL
 *
 * @return string
 */
function aichat_get_upgrade_url()
{
	if (function_exists('aichat_fs')) {
		$fs = aichat_fs();
		if ($fs) {
			return $fs->get_upgrade_url();
		}
	}

	// Fallback URL
	return 'https://axiachat.com/pricing/';
}

/**
 * Add Pricing menu page (Freemius will handle this when SDK is active)
 */
add_action('admin_menu', function () {
	// Only add if Freemius is not handling it
	if (function_exists('aichat_fs') && aichat_fs()) {
		return;
		// Freemius handles the menu
	}
	// Add placeholder pricing page
	add_submenu_page(
		'aichat-settings',
		__('Upgrade to Pro', 'axiachat-ai'),
		'<span style="color:#f0b849">★ ' . __('Go Pro', 'axiachat-ai') . '</span>',
		'manage_options',
		'aichat-settings-pricing',
		'aichat_render_pricing_page'
	);
}, 100);
/**
 * Render placeholder pricing page
 */
function aichat_render_pricing_page()
{
	?>
    <div class="wrap">
        <h1><?php
	esc_html_e('Upgrade to AxiaChat AI Pro', 'axiachat-ai');
	?></h1>
        
        <div class="card" style="max-width: 600px; margin-top: 20px; padding: 20px;">
            <h2 style="color: #2271b1;"><span class="dashicons dashicons-star-filled" style="color: #f0b849;"></span> Pro Features</h2>
            
            <ul style="font-size: 14px; line-height: 2;">
                <li><strong>✓ Unlimited Leads</strong> - No more 25 lead limit</li>
                <li><strong>✓ Unlimited Appointments</strong> - Coming soon</li>
                <li><strong>✓ Priority Support</strong> - Get help faster</li>
                <li><strong>✓ Future Features</strong> - All new add-ons included</li>
            </ul>
            
            <hr>
            
            <p style="font-size: 16px;">
                <strong>Enter your license key:</strong>
            </p>
            
            <form method="post" action="<?php
	echo esc_url(admin_url('admin-post.php'));
	?>">
                <?php
	wp_nonce_field('aichat_activate_license', 'aichat_license_nonce');
	?>
                <input type="hidden" name="action" value="aichat_activate_license">
                
                <input type="text" 
                       name="license_key" 
                       placeholder="XXXX-XXXX-XXXX-XXXX" 
                       style="width: 300px; padding: 8px; font-family: monospace;"
                       value="<?php
	echo esc_attr(get_option('aichat_pro_license_key', ''));
	?>">
                
                <button type="submit" class="button button-primary" style="padding: 4px 20px;">
                    <?php
	esc_html_e('Activate License', 'axiachat-ai');
	?>
                </button>
            </form>
            
            <p style="margin-top: 20px;">
                <a href="https://axiachat.com/pricing/" target="_blank" class="button button-secondary">
                    <?php
	esc_html_e('Purchase License', 'axiachat-ai');
	?> →
                </a>
            </p>
        </div>
    </div>
    <?php
}

/**
 * Handle license activation
 */
add_action('admin_post_aichat_activate_license', function () {
	if (!current_user_can('manage_options')) {
		wp_die('Unauthorized');
	}
	check_admin_referer('aichat_activate_license', 'aichat_license_nonce');
	$license = (isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '');
	// Save license
	update_option('aichat_pro_license_key', $license);
	// Clear cached validation
	delete_transient('aichat_license_valid');
	delete_transient('aichat_leads_license_status');
	// Redirect back (wp_safe_redirect ensures the URL is in the allowed hosts list)
	wp_safe_redirect(add_query_arg('license_updated', '1', admin_url('admin.php?page=aichat-settings-pricing')));
	exit;
});
