<?php

/**
 * Google Sheets OAuth Handler for Leads
 *
 * Handles OAuth 2.0 authentication flow for Google Sheets API.
 * Uses the same Google Cloud app credentials as Calendar.
 *
 * @package AIChat
 * @subpackage Leads
 */

if (! defined('ABSPATH')) {
	exit;
}

class AIChat_Leads_GSheets_OAuth
{
	/** Google OAuth URLs */
	public const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
	public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

	/** Scopes - Sheets + email for user info */
	public const SCOPES = 'https://www.googleapis.com/auth/spreadsheets https://www.googleapis.com/auth/userinfo.email';

	/** Option keys for storing tokens (separate from Calendar) */
	public const OPTION_ACCESS_TOKEN = 'aichat_leads_gsheets_access_token';
	public const OPTION_REFRESH_TOKEN = 'aichat_leads_gsheets_refresh_token';
	public const OPTION_TOKEN_EXPIRY = 'aichat_leads_gsheets_token_expiry';
	public const OPTION_USER_EMAIL = 'aichat_leads_gsheets_user_email';

	/** Shared OAuth credentials (same as Calendar) */
	public const CLIENT_ID = '353820719147-fq0u9scoqkgakoq42iu2d0mdn27pltj7.apps.googleusercontent.com';
	public const CLIENT_SECRET = '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';

	/** Central OAuth callback URL for Google Cloud App verification */
	public const CENTRAL_CALLBACK_URL = 'https://axiachat.org/site/wp-admin/admin.php?page=aichat-leads&tab=settings&gsheets_callback=1';

	/**
	 * Get OAuth credentials
	 * Uses hardcoded shared credentials (same Google Cloud app as Calendar)
	 */
	private static function get_credentials()
	{
		return [
			'client_id' => self::CLIENT_ID,
			'client_secret' => self::CLIENT_SECRET,
		];
	}

	/**
	 * Check if credentials are configured (always true with hardcoded creds)
	 */
	public static function has_credentials()
	{
		return true;
	}

	/**
	 * Check if connected (has valid tokens)
	 */
	public static function is_connected()
	{
		$access_token = get_option(self::OPTION_ACCESS_TOKEN);
		$refresh_token = get_option(self::OPTION_REFRESH_TOKEN);

		return ! empty($access_token) || ! empty($refresh_token);
	}

	/**
	 * Get the redirect URI for OAuth callback (central URL)
	 * Uses central URL for Google Cloud App verification
	 */
	public static function get_redirect_uri()
	{
		return self::CENTRAL_CALLBACK_URL;
	}

	/**
	 * Get the local callback URL (where central server should redirect back)
	 */
	public static function get_local_callback_url()
	{
		return admin_url('admin.php?page=aichat-leads&tab=settings&gsheets_callback=1');
	}

	/**
	 * Generate OAuth authorization URL
	 */
	public static function get_auth_url()
	{
		$creds = self::get_credentials();

		if (empty($creds['client_id'])) {
			return '';
		}

		// Generate nonce for CSRF protection
		$nonce = wp_create_nonce('aichat_gsheets_oauth');
		set_transient('aichat_gsheets_oauth_state', $nonce, HOUR_IN_SECONDS);

		// Encode state with nonce and return URL for central server redirect
		$state_data = [
			'nonce' => $nonce,
			'return_url' => self::get_local_callback_url(),
		];
		$state = base64_encode(wp_json_encode($state_data));

		$params = [
			'client_id' => $creds['client_id'],
			'redirect_uri' => self::get_redirect_uri(),
			'response_type' => 'code',
			'scope' => self::SCOPES,
			'access_type' => 'offline',
			'prompt' => 'consent', // Force consent to get refresh token
			'state' => $state,
		];

		return self::AUTH_URL . '?' . http_build_query($params);
	}

	/**
	 * Exchange authorization code for tokens
	 */
	public static function exchange_code($code)
	{
		$creds = self::get_credentials();

		$response = wp_remote_post(self::TOKEN_URL, [
			'timeout' => 30,
			'body' => [
				'code' => $code,
				'client_id' => $creds['client_id'],
				'client_secret' => $creds['client_secret'],
				'redirect_uri' => self::get_redirect_uri(),
				'grant_type' => 'authorization_code',
			],
		]);

		if (is_wp_error($response)) {
			aichat_log_debug('[GSheets OAuth] Token exchange error', ['error' => $response->get_error_message()]);

			return $response;
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);

		if (isset($body['error'])) {
			aichat_log_debug('[GSheets OAuth] Token exchange failed', $body);

			return new WP_Error('oauth_error', $body['error_description'] ?? $body['error']);
		}

		// Store tokens
		self::store_tokens($body);

		// Get user email for display
		self::fetch_user_info($body['access_token']);

		return true;
	}

	/**
	 * Store OAuth tokens securely
	 */
	private static function store_tokens($token_data)
	{
		if (isset($token_data['access_token'])) {
			update_option(self::OPTION_ACCESS_TOKEN, self::encrypt_token($token_data['access_token']));
		}

		if (isset($token_data['refresh_token'])) {
			update_option(self::OPTION_REFRESH_TOKEN, self::encrypt_token($token_data['refresh_token']));
		}

		if (isset($token_data['expires_in'])) {
			$expiry = time() + (int) $token_data['expires_in'];
			update_option(self::OPTION_TOKEN_EXPIRY, $expiry);
		}
	}

	/**
	 * Fetch user info to display connected account
	 */
	private static function fetch_user_info($access_token)
	{
		$response = wp_remote_get('https://www.googleapis.com/oauth2/v2/userinfo', [
			'headers' => [
				'Authorization' => 'Bearer ' . $access_token,
			],
		]);

		if (is_wp_error($response)) {
			aichat_log_debug('[GSheets OAuth] Failed to fetch user info', ['error' => $response->get_error_message()]);

			return;
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);
		aichat_log_debug('[GSheets OAuth] User info response', $body);

		if (isset($body['email'])) {
			update_option(self::OPTION_USER_EMAIL, sanitize_email($body['email']));
			aichat_log_debug('[GSheets OAuth] Saved user email', ['email' => $body['email']]);
		}
	}

	/**
	 * Get valid access token, refreshing if needed
	 */
	public static function get_access_token()
	{
		$expiry = get_option(self::OPTION_TOKEN_EXPIRY, 0);

		// If token expires in less than 5 minutes, refresh it
		if ($expiry < (time() + 300)) {
			$refreshed = self::refresh_access_token();
			if (is_wp_error($refreshed)) {
				return $refreshed;
			}
		}

		$encrypted = get_option(self::OPTION_ACCESS_TOKEN);
		if (empty($encrypted)) {
			return new WP_Error('no_token', __('Not connected to Google Sheets', 'axiachat-ai'));
		}

		return self::decrypt_token($encrypted);
	}

	/**
	 * Refresh the access token using refresh token
	 */
	public static function refresh_access_token()
	{
		$refresh_token = get_option(self::OPTION_REFRESH_TOKEN);
		if (empty($refresh_token)) {
			return new WP_Error('no_refresh_token', __('No refresh token available', 'axiachat-ai'));
		}

		$refresh_token = self::decrypt_token($refresh_token);
		$creds = self::get_credentials();

		$response = wp_remote_post(self::TOKEN_URL, [
			'timeout' => 30,
			'body' => [
				'refresh_token' => $refresh_token,
				'client_id' => $creds['client_id'],
				'client_secret' => $creds['client_secret'],
				'grant_type' => 'refresh_token',
			],
		]);

		if (is_wp_error($response)) {
			return $response;
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);

		if (isset($body['error'])) {
			// If refresh fails, clear tokens (user needs to reconnect)
			self::disconnect();

			return new WP_Error('refresh_failed', $body['error_description'] ?? $body['error']);
		}

		self::store_tokens($body);

		return true;
	}

	/**
	 * Disconnect (clear all tokens)
	 */
	public static function disconnect()
	{
		delete_option(self::OPTION_ACCESS_TOKEN);
		delete_option(self::OPTION_REFRESH_TOKEN);
		delete_option(self::OPTION_TOKEN_EXPIRY);
		delete_option(self::OPTION_USER_EMAIL);
	}

	/**
	 * Get connected user email
	 */
	public static function get_user_email()
	{
		return get_option(self::OPTION_USER_EMAIL, '');
	}

	/**
	 * Encrypt token for storage
	 */
	private static function encrypt_token($token)
	{
		if (! function_exists('openssl_encrypt')) {
			return base64_encode($token);
		}

		$key = self::get_encryption_key();
		$iv = openssl_random_pseudo_bytes(16);
		$encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);

		return base64_encode($iv . $encrypted);
	}

	/**
	 * Decrypt token from storage
	 */
	private static function decrypt_token($encrypted)
	{
		if (! function_exists('openssl_decrypt')) {
			return base64_decode($encrypted);
		}

		$key = self::get_encryption_key();
		$data = base64_decode($encrypted);
		$iv = substr($data, 0, 16);
		$encrypted_token = substr($data, 16);

		return openssl_decrypt($encrypted_token, 'AES-256-CBC', $key, 0, $iv);
	}

	/**
	 * Get encryption key
	 */
	private static function get_encryption_key()
	{
		if (defined('SECURE_AUTH_KEY') && SECURE_AUTH_KEY) {
			return hash('sha256', SECURE_AUTH_KEY . 'aichat_leads_gsheets', true);
		}

		return hash('sha256', 'aichat_leads_gsheets_default_key', true);
	}
}
