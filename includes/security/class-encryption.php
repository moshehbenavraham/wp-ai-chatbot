<?php

/**
 * AES-256-GCM encryption for sensitive plugin settings (API keys, credentials).
 *
 * Wire format: base64( json({ v, m, iv, ct, tag }) )
 * Key derivation: SHA-256 of WPAIC_MASTER_KEY constant, or wp_salt('auth') fallback.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Security;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Encryption
 *
 * Provides AES-256-GCM authenticated encryption for plugin-managed secrets.
 * Transparently falls back to plaintext when PHP's OpenSSL extension is missing
 * (with an admin notice and health-endpoint degradation flag).
 */
final class Encryption
{
	/** Cipher used for all encryption operations. */
	private const CIPHER = 'aes-256-gcm';

	/** IV length in bytes (GCM recommended: 12 bytes / 96 bits). */
	private const IV_LENGTH = 12;

	/** GCM authentication-tag length in bytes. */
	private const TAG_LENGTH = 16;

	/** Envelope version — bumped on any format change to allow migration. */
	private const ENVELOPE_VERSION = 1;

	/**
	 * Sensitive option keys that are auto-encrypted by Settings_Manager.
	 * Extensions may add entries via the `wpaic_sensitive_fields` filter.
	 */
	private const SENSITIVE_FIELDS = [
		'wpaic_provider_openai_key',
		'wpaic_provider_claude_key',
		'wpaic_provider_gemini_key',
		'wpaic_provider_deepseek_key',
		'wpaic_provider_grok_key',
		'wpaic_provider_voyage_key',
		'wpaic_rag_pinecone_key',
		'wpaic_rag_pinecone_endpoint',
	];

	// =========================================================================
	// Public API
	// =========================================================================

	/**
	 * Encrypt a plaintext value.
	 *
	 * Returns the plaintext unchanged (and emits an admin notice) when the
	 * OpenSSL extension is unavailable. Callers must not assume the returned
	 * string is always encrypted — use is_encrypted() to confirm.
	 *
	 * @param  string $plaintext Value to encrypt.
	 * @return string            Base64-JSON envelope, or original plaintext on failure.
	 */
	public static function encrypt(string $plaintext): string
	{
		if (! self::is_openssl_available()) {
			self::trigger_degraded_notice();

			return $plaintext;
		}

		if ('' === $plaintext) {
			return '';
		}

		$key = self::get_derived_key();
		$iv = random_bytes(self::IV_LENGTH);
		$tag = '';

		$ciphertext = openssl_encrypt(
			$plaintext,
			self::CIPHER,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'',
			self::TAG_LENGTH
		);

		if (false === $ciphertext) {
			// OpenSSL error — fall back to plaintext and log.
			if (function_exists('wpaic_structured_log')) {
				wpaic_structured_log('error', 'Encryption failed', [
					'openssl_error' => openssl_error_string(),
				], 'security');
			}

			return $plaintext;
		}

		$envelope = [
			'v' => self::ENVELOPE_VERSION,
			'm' => self::CIPHER,
			'iv' => base64_encode($iv),
			'ct' => base64_encode($ciphertext),
			'tag' => base64_encode($tag),
		];

		return base64_encode((string) wp_json_encode($envelope));
	}

	/**
	 * Decrypt an encrypted envelope.
	 *
	 * Returns the stored value unchanged when OpenSSL is unavailable or the
	 * envelope cannot be parsed (graceful degradation for plaintext migration).
	 *
	 * @param  string $stored Stored encrypted envelope (or plaintext).
	 * @return string         Decrypted plaintext, or original value on failure.
	 */
	public static function decrypt(string $stored): string
	{
		if ('' === $stored || ! self::is_openssl_available()) {
			return $stored;
		}

		$envelope = self::parse_envelope($stored);
		if (null === $envelope) {
			// Not an encrypted envelope — return as-is (supports plaintext migration).
			return $stored;
		}

		$key = self::get_derived_key();
		$iv = base64_decode($envelope['iv'], true);
		$ciphertext = base64_decode($envelope['ct'], true);
		$tag = base64_decode($envelope['tag'], true);

		if (false === $iv || false === $ciphertext || false === $tag) {
			return $stored;
		}

		$plaintext = openssl_decrypt(
			$ciphertext,
			self::CIPHER,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		if (false === $plaintext) {
			// Decryption failure — key may have changed; return stored value.
			if (function_exists('wpaic_structured_log')) {
				wpaic_structured_log('warn', 'Decryption failed — key mismatch or corrupt envelope', [], 'security');
			}

			return $stored;
		}

		return $plaintext;
	}

	/**
	 * Determine whether $value is an encrypted envelope produced by this class.
	 *
	 * @param  mixed $value Any stored option value.
	 * @return bool
	 */
	public static function is_encrypted($value): bool
	{
		if (! is_string($value) || '' === $value) {
			return false;
		}

		return null !== self::parse_envelope($value);
	}

	/**
	 * Return the list of option keys that require encryption at rest.
	 *
	 * Extensions add entries via the `wpaic_sensitive_fields` filter.
	 *
	 * @return string[]
	 */
	public static function get_sensitive_fields(): array
	{
		return (array) apply_filters('wpaic_sensitive_fields', self::SENSITIVE_FIELDS);
	}

	/**
	 * Derive the 256-bit encryption key from site-specific material.
	 *
	 * Priority:
	 *   1. WPAIC_MASTER_KEY constant (operator-defined in wp-config.php)
	 *   2. WordPress AUTH_KEY salt (auto-generated, survives most site changes)
	 *
	 * @return string 32 bytes of raw key material.
	 */
	public static function get_derived_key(): string
	{
		$source = defined('WPAIC_MASTER_KEY') && '' !== WPAIC_MASTER_KEY
			? WPAIC_MASTER_KEY
			: wp_salt('auth');

		return hash('sha256', (string) $source, true);
	}

	/**
	 * Check whether OpenSSL is available with the required cipher.
	 *
	 * @return bool
	 */
	public static function is_openssl_available(): bool
	{
		return function_exists('openssl_encrypt')
			&& in_array(self::CIPHER, openssl_get_cipher_methods(), true);
	}

	// =========================================================================
	// Internal helpers
	// =========================================================================

	/**
	 * Parse a base64-JSON envelope string.
	 *
	 * @param  string $value Candidate value.
	 * @return array|null    Parsed envelope array, or null if not a valid envelope.
	 */
	private static function parse_envelope(string $value): ?array
	{
		$json = base64_decode($value, true);
		if (false === $json) {
			return null;
		}

		$data = json_decode($json, true);
		if (! is_array($data)) {
			return null;
		}

		foreach (['v', 'm', 'iv', 'ct', 'tag'] as $required) {
			if (! isset($data[$required])) {
				return null;
			}
		}

		if (! is_int($data['v']) || $data['v'] < 1) {
			return null;
		}

		return $data;
	}

	/**
	 * Emit a persistent admin notice and health-endpoint warning when OpenSSL
	 * is unavailable so operators know API keys are stored in plaintext.
	 *
	 * @return void
	 */
	private static function trigger_degraded_notice(): void
	{
		if (! is_admin()) {
			return;
		}

		add_action('admin_notices', static function (): void {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__(
				'WP AI Chatbot: OpenSSL is not available. API keys are stored in plaintext. '
				. 'Please enable the PHP OpenSSL extension.',
				'wp-ai-chatbot'
			);
			echo '</p></div>';
		});
	}
}
