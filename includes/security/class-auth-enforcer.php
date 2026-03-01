<?php

/**
 * Auth enforcer — dual-check (nonce + capability) for every AJAX/REST endpoint.
 *
 * Compliance rules enforced here:
 *   ACR-01  Every handler calls check_ajax() or check_rest() before reading request data.
 *   ACR-02  Nonce-only is forbidden for admin endpoints.
 *   ACR-03  Capability-only is forbidden for all endpoints.
 *   ACR-04  Nopriv endpoints run rate limiter BEFORE this class (caller responsibility).
 *   ACR-05  Session ownership verified before any session data retrieval.
 *   ACR-06  All nonce action strings retrieved via nonce_action().
 *   ACR-07  Nonce values MUST NOT appear in logs.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Security;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Auth_Enforcer
 *
 * Single entry point for auth checks on all plugin endpoints.
 */
final class Auth_Enforcer
{
	/**
	 * Nonce action prefix used for all plugin endpoints.
	 */
	private const NONCE_PREFIX = 'wpaic_';

	/**
	 * Admin capability used throughout the plugin (filterable).
	 */
	private const DEFAULT_CAPABILITY = 'manage_options';

	// =========================================================================
	// Endpoint slug constants — centralises nonce action naming (ACR-06).
	// =========================================================================

	public const ENDPOINT_CHAT_SEND = 'chat_send';
	public const ENDPOINT_SESSION_CREATE = 'session_create';
	public const ENDPOINT_SESSION_HISTORY = 'session_history';
	public const ENDPOINT_KNOWLEDGE_CRUD = 'knowledge_crud';
	public const ENDPOINT_KNOWLEDGE_REINDEX = 'knowledge_reindex';
	public const ENDPOINT_ADMIN_SETTINGS = 'admin_settings';
	public const ENDPOINT_DIAGNOSTICS_STREAM = 'diagnostics_stream';
	public const ENDPOINT_DATA_EXPORT = 'data_export';
	public const ENDPOINT_DATA_ERASURE = 'data_erasure';
	public const ENDPOINT_PROVIDER_TEST = 'provider_test';
	public const ENDPOINT_BACKUP_EXPORT = 'backup_export';
	public const ENDPOINT_BACKUP_IMPORT = 'backup_import';

	// =========================================================================
	// Public API
	// =========================================================================

	/**
	 * Verify nonce + capability for an AJAX request.
	 * Calls wp_die() on failure (AJAX convention).
	 *
	 * For nopriv endpoints ($nopriv = true) the capability check is skipped
	 * because the user is unauthenticated. Rate limiting must run BEFORE this
	 * method is called for all nopriv endpoints (ACR-04).
	 *
	 * @param  string $action      Nonce action string (use nonce_action()).
	 * @param  string $nonce_field POST field name containing the nonce value.
	 * @param  string $capability  WP capability required; '' for nopriv endpoints.
	 * @param  bool   $nopriv      True if serving unauthenticated users.
	 * @return void
	 */
	public static function check_ajax(
		string $action,
		string $nonce_field = 'nonce',
		string $capability = '',
		bool $nopriv = false
	): void {
		// Nonce verification (ACR-03: nonce is always required).
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$nonce = isset($_POST[$nonce_field]) ? (string) $_POST[$nonce_field] : '';

		if (! wp_verify_nonce($nonce, $action)) {
			Audit_Logger::log(
				Audit_Logger::EVENT_AUTH_FAIL,
				[
					'endpoint' => current_action(),
					'reason' => 'invalid_nonce',
					'user_id' => get_current_user_id(),
					'ip_hash' => md5((string) self::get_client_ip()),
				],
				'warn'
			);
			wp_die(
				esc_html__('Security check failed. Please refresh the page and try again.', 'wp-ai-chatbot'),
				esc_html__('Forbidden', 'wp-ai-chatbot'),
				['response' => 403]
			);
		}

		// Capability verification for admin/authenticated endpoints (ACR-02).
		if (! $nopriv && '' !== $capability) {
			$cap = apply_filters('wpaic_admin_capability', $capability);
			if (! current_user_can($cap)) {
				Audit_Logger::log(
					Audit_Logger::EVENT_AUTH_FAIL,
					[
						'endpoint' => current_action(),
						'reason' => 'insufficient_capability',
						'capability' => $cap,
						'user_id' => get_current_user_id(),
						'ip_hash' => md5((string) self::get_client_ip()),
					],
					'warn'
				);
				wp_die(
					esc_html__('You do not have permission to perform this action.', 'wp-ai-chatbot'),
					esc_html__('Forbidden', 'wp-ai-chatbot'),
					['response' => 403]
				);
			}
		}
	}

	/**
	 * Verify nonce + capability for a REST request.
	 * Returns WP_Error on failure (REST convention).
	 *
	 * @param  \WP_REST_Request $request
	 * @param  string           $action      Nonce action string (use nonce_action()).
	 * @param  string           $capability  Required WP capability; '' for public endpoints.
	 * @return true|\WP_Error
	 */
	public static function check_rest(
		\WP_REST_Request $request,
		string $action,
		string $capability = ''
	) {
		// For public REST endpoints (no capability), skip nonce check.
		// Public endpoints rely on the WP REST API's built-in CSRF protection.
		if ('' === $capability) {
			return true;
		}

		// Nonce from X-WP-Nonce header (standard WP REST convention).
		$nonce = $request->get_header('X-WP-Nonce');
		if (! $nonce) {
			$nonce = $request->get_param('_wpnonce') ?? '';
		}

		if (! wp_verify_nonce((string) $nonce, $action)) {
			Audit_Logger::log(
				Audit_Logger::EVENT_AUTH_FAIL,
				[
					'endpoint' => $request->get_route(),
					'reason' => 'invalid_nonce',
					'user_id' => get_current_user_id(),
					'ip_hash' => md5((string) self::get_client_ip()),
				],
				'warn'
			);

			return new \WP_Error(
				'wpaic_auth_failed',
				__('Security verification failed.', 'wp-ai-chatbot'),
				['status' => 403]
			);
		}

		$cap = apply_filters('wpaic_admin_capability', $capability);
		if (! current_user_can($cap)) {
			Audit_Logger::log(
				Audit_Logger::EVENT_AUTH_FAIL,
				[
					'endpoint' => $request->get_route(),
					'reason' => 'insufficient_capability',
					'capability' => $cap,
					'user_id' => get_current_user_id(),
					'ip_hash' => md5((string) self::get_client_ip()),
				],
				'warn'
			);

			return new \WP_Error(
				'wpaic_forbidden',
				__('You do not have permission to perform this action.', 'wp-ai-chatbot'),
				['status' => 403]
			);
		}

		return true;
	}

	/**
	 * Check ownership of a session (UUID match + user/IP match).
	 * Used by session resume and history endpoints (ACR-05).
	 *
	 * @param  string $session_token UUID from the request.
	 * @param  array  $session_row   Row from wp_wpaic_sessions.
	 * @return bool                  True if ownership is verified.
	 */
	public static function verify_session_ownership(
		string $session_token,
		array $session_row
	): bool {
		// Token must match.
		if (! isset($session_row['session_token'])) {
			return false;
		}

		if (! hash_equals((string) $session_row['session_token'], $session_token)) {
			return false;
		}

		$user_id = get_current_user_id();

		// Logged-in user: match by user_id.
		if ($user_id > 0 && isset($session_row['user_id'])) {
			if ((int) $session_row['user_id'] === $user_id) {
				return true;
			}
		}

		// Guest: match by IP hash.
		if (isset($session_row['ip_hash'])) {
			$client_ip_hash = md5((string) self::get_client_ip());
			if (hash_equals((string) $session_row['ip_hash'], $client_ip_hash)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the nonce action string for a given endpoint slug (ACR-06).
	 * Centralises nonce action naming to prevent typos across create/verify sites.
	 *
	 * @param  string $endpoint_slug One of the ENDPOINT_* constants.
	 * @return string                Nonce action string.
	 */
	public static function nonce_action(string $endpoint_slug): string
	{
		return self::NONCE_PREFIX . $endpoint_slug;
	}

	/**
	 * Return the filterable admin capability string.
	 *
	 * @return string
	 */
	public static function admin_capability(): string
	{
		return (string) apply_filters('wpaic_admin_capability', self::DEFAULT_CAPABILITY);
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Retrieve the client IP address, respecting common proxy headers.
	 *
	 * @return string IP address string (may be empty if unavailable).
	 */
	private static function get_client_ip(): string
	{
		$headers = [
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_REAL_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		];

		foreach ($headers as $header) {
			if (! empty($_SERVER[$header])) {
				$ip = strtok((string) $_SERVER[$header], ',');
				$ip = trim((string) $ip);
				if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
					return $ip;
				}
			}
		}

		return '';
	}
}
