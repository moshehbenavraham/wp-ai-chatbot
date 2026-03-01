<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use WPAIC\Security\Encryption;

/**
 * Tests for WPAIC\Security\Encryption
 *
 * Covers AES-256-GCM encrypt/decrypt round-trips, graceful plaintext
 * fallback, envelope detection, and key derivation.
 */
final class EncryptionTest extends TestCase
{
	private static bool $loaded = false;

	public static function setUpBeforeClass(): void
	{
		if (self::$loaded) {
			return;
		}

		require_once __DIR__ . '/stubs/class-wp-error.php';
		require_once __DIR__ . '/stubs/wp-core-stubs.php';

		// Provide a deterministic master key so tests don't rely on wp_salt().
		if (! defined('WPAIC_MASTER_KEY')) {
			define('WPAIC_MASTER_KEY', 'test-master-key-32-bytes-exactly!!');
		}

		self::$loaded = true;
	}

	protected function setUp(): void
	{
		\WpTestHelpers::reset();
	}

	// ── Round-trip ────────────────────────────────────────────────────────────

	public function testEncryptDecryptRoundTrip(): void
	{
		if (! Encryption::is_openssl_available()) {
			$this->markTestSkipped('OpenSSL not available in this environment.');
		}

		$plaintext = 'sk-openai-super-secret-api-key-1234567890';
		$ciphertext = Encryption::encrypt($plaintext);

		$this->assertNotSame($plaintext, $ciphertext, 'Encrypted value must differ from plaintext.');
		$this->assertSame($plaintext, Encryption::decrypt($ciphertext));
	}

	public function testEncryptDecryptRoundTripUnicode(): void
	{
		if (! Encryption::is_openssl_available()) {
			$this->markTestSkipped('OpenSSL not available.');
		}

		$plaintext = 'API-Schlüssel: こんにちは 🔑';
		$this->assertSame($plaintext, Encryption::decrypt(Encryption::encrypt($plaintext)));
	}

	public function testEmptyStringEncryptsToEmpty(): void
	{
		$this->assertSame('', Encryption::encrypt(''));
	}

	public function testEmptyStringDecryptsToEmpty(): void
	{
		$this->assertSame('', Encryption::decrypt(''));
	}

	// ── is_encrypted ─────────────────────────────────────────────────────────

	public function testIsEncryptedReturnsTrueForValidEnvelope(): void
	{
		if (! Encryption::is_openssl_available()) {
			$this->markTestSkipped('OpenSSL not available.');
		}

		$encrypted = Encryption::encrypt('secret');
		$this->assertTrue(Encryption::is_encrypted($encrypted));
	}

	public function testIsEncryptedReturnsFalseForPlaintext(): void
	{
		$this->assertFalse(Encryption::is_encrypted('sk-plaintext-key'));
	}

	public function testIsEncryptedReturnsFalseForEmptyString(): void
	{
		$this->assertFalse(Encryption::is_encrypted(''));
	}

	public function testIsEncryptedReturnsFalseForNonString(): void
	{
		$this->assertFalse(Encryption::is_encrypted(42));
		$this->assertFalse(Encryption::is_encrypted(null));
		$this->assertFalse(Encryption::is_encrypted([]));
	}

	// ── Graceful degradation (plaintext passthrough) ──────────────────────────

	public function testDecryptPlaintextPassesThrough(): void
	{
		// A plain text value that has never been encrypted should pass through unchanged.
		$plaintext = 'not-an-encrypted-envelope';
		$this->assertSame($plaintext, Encryption::decrypt($plaintext));
	}

	public function testDecryptBase64NonJsonPassesThrough(): void
	{
		// base64 string that decodes to non-JSON should be returned unchanged.
		$stored = base64_encode('this is not json');
		$this->assertSame($stored, Encryption::decrypt($stored));
	}

	// ── Key derivation ────────────────────────────────────────────────────────

	public function testGetDerivedKeyIs32Bytes(): void
	{
		$key = Encryption::get_derived_key();
		$this->assertSame(32, strlen($key), 'Derived key must be 32 raw bytes (SHA-256 output).');
	}

	public function testGetDerivedKeyIsDeterministic(): void
	{
		$key1 = Encryption::get_derived_key();
		$key2 = Encryption::get_derived_key();
		$this->assertSame($key1, $key2, 'Repeated calls must return the same key.');
	}

	// ── Sensitive fields ──────────────────────────────────────────────────────

	public function testGetSensitiveFieldsReturnsArray(): void
	{
		$fields = Encryption::get_sensitive_fields();
		$this->assertIsArray($fields);
		$this->assertNotEmpty($fields);
	}

	public function testGetSensitiveFieldsContainsExpectedKeys(): void
	{
		$fields = Encryption::get_sensitive_fields();
		$this->assertContains('wpaic_provider_openai_key', $fields);
		$this->assertContains('wpaic_provider_claude_key', $fields);
		$this->assertContains('wpaic_provider_gemini_key', $fields);
	}

	// ── Different values produce different ciphertexts (IV randomness) ────────

	public function testEachEncryptionProducesDifferentOutput(): void
	{
		if (! Encryption::is_openssl_available()) {
			$this->markTestSkipped('OpenSSL not available.');
		}

		$plaintext = 'same-input-every-time';
		$ct1 = Encryption::encrypt($plaintext);
		$ct2 = Encryption::encrypt($plaintext);

		// Each call uses a random IV, so the ciphertext must differ.
		$this->assertNotSame($ct1, $ct2, 'Each encryption should use a fresh random IV.');
	}

	// ── OpenSSL availability ──────────────────────────────────────────────────

	public function testIsOpensslAvailableReturnsBool(): void
	{
		$this->assertIsBool(Encryption::is_openssl_available());
	}
}
