<?php

/**
 * Settings Manager — transparent encrypt/decrypt for sensitive options.
 *
 * Wraps get_option() / update_option() with automatic encryption for
 * fields listed in Encryption::get_sensitive_fields(). All domain code
 * reads/writes settings through this class, never directly via WP functions.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Admin;

use WPAIC\Security\Encryption;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Settings_Manager
 *
 * Singleton that provides transparent encrypt/decrypt, masked display,
 * and field registration via the WP Settings API.
 *
 * @since 1.0.0
 * @package WPAIC\Admin
 */
final class Settings_Manager
{
	/** @var Settings_Manager|null Singleton instance. */
	private static $instance = null;

	/** Private constructor — use get_instance(). */
	private function __construct()
	{
	}

	/**
	 * Return (or lazily create) the singleton instance.
	 *
	 * @return Settings_Manager
	 */
	public static function get_instance(): self
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	// =========================================================================
	// Read / Write API
	// =========================================================================

	/**
	 * Get a plugin option, transparently decrypting encrypted values.
	 *
	 * For sensitive fields (API keys): if the stored value is an encrypted
	 * envelope it is decrypted before returning. Plaintext values pass through
	 * unchanged (supports migration from unencrypted installs).
	 *
	 * @param  string $key     wp_options option name.
	 * @param  mixed  $default Default value if option does not exist.
	 * @return mixed           Decrypted (or raw) option value.
	 */
	public static function get(string $key, $default = null)
	{
		$value = get_option($key, $default);

		if (is_string($value) && Encryption::is_encrypted($value)) {
			return Encryption::decrypt($value);
		}

		return $value;
	}

	/**
	 * Set a plugin option, transparently encrypting sensitive values.
	 *
	 * @param  string $key   wp_options option name.
	 * @param  mixed  $value Value to store.
	 * @return bool          True if the value was updated.
	 */
	public static function set(string $key, $value): bool
	{
		if (is_string($value) && self::is_sensitive_field($key)) {
			if ('' !== $value && ! Encryption::is_encrypted($value)) {
				$value = Encryption::encrypt($value);
			}
		}

		return update_option($key, $value);
	}

	/**
	 * Return a masked display value for a sensitive field.
	 *
	 * Shows '••••••••' + last 4 characters, or empty string if not set.
	 * Use in admin UI to indicate a key is stored without revealing it.
	 *
	 * @param  string $key Option key.
	 * @return string      Masked value, or '' if not set.
	 */
	public static function get_masked(string $key): string
	{
		$value = self::get($key, '');

		if ('' === (string) $value) {
			return '';
		}

		$str = (string) $value;

		return str_repeat('•', 8) . substr($str, -4);
	}

	/**
	 * Check whether a key is in the sensitive fields list.
	 *
	 * @param  string $key Option key.
	 * @return bool
	 */
	public static function is_sensitive_field(string $key): bool
	{
		return in_array($key, Encryption::get_sensitive_fields(), true);
	}

	// =========================================================================
	// WP Settings API registration
	// =========================================================================

	/**
	 * Register all plugin settings with the WP Settings API.
	 * Called on `admin_init` by Plugin::on_admin_init().
	 *
	 * Each page controller's register_settings() is called here, delegating
	 * field-level registration to the appropriate controller.
	 *
	 * @return void
	 */
	public function register_settings(): void
	{
		// Delegate to each page controller if Admin_Router is available.
		if (class_exists('WPAIC\Admin\Admin_Router')) {
			Admin_Router::get_instance()->register_all_page_settings();
		}

		/**
		 * Fires after plugin settings are registered on admin_init.
		 * Extension modules register their settings here.
		 *
		 * @since 1.0.0
		 */
		do_action('wpaic_register_settings');
	}

	// =========================================================================
	// Sanitization callback builders
	// =========================================================================

	/**
	 * Build a sanitize_callback for a sensitive field (encrypt on save).
	 *
	 * Suitable for use in register_setting() calls.
	 *
	 * @return callable
	 */
	public static function sensitive_sanitize_callback(): callable
	{
		return static function ($value): string {
			$value = sanitize_text_field((string) $value);

			if ('' === $value) {
				return '';
			}

			// Already encrypted (re-save without change): pass through.
			if (Encryption::is_encrypted($value)) {
				return $value;
			}

			return Encryption::encrypt($value);
		};
	}

	/**
	 * Build a sanitize_callback that routes through Sanitizer.
	 *
	 * @param  string $type   Sanitizer type constant.
	 * @param  array  $params Type-specific params.
	 * @return callable
	 */
	public static function sanitize_callback(string $type, array $params = []): callable
	{
		return static function ($value) use ($type, $params) {
			if (! class_exists('WPAIC\Security\Sanitizer')) {
				return sanitize_text_field((string) $value);
			}

			return \WPAIC\Security\Sanitizer::sanitize($value, $type, $params);
		};
	}
}
