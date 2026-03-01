<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use WPAIC\Runtime\Session_Manager;

/**
 * Unit tests for Session_Manager pure-logic methods.
 *
 * These tests cover every static helper that requires NO database access:
 *   - Class constants
 *   - is_valid_uuid()
 *   - validate_ownership()
 *   - get_client_ip()
 *
 * Methods that require $wpdb (create, resume, close, log_message, etc.) are
 * integration-level and are not included here — they belong in a WP-test suite
 * that spins up a real test database.
 *
 * @covers \WPAIC\Runtime\Session_Manager
 */
class SessionManagerTest extends TestCase
{
	// =========================================================================
	// Test infrastructure
	// =========================================================================

	public static function setUpBeforeClass(): void
	{
		require_once __DIR__ . '/stubs/wp-core-stubs.php';
	}

	protected function setUp(): void
	{
		\WpTestHelpers::reset();
		// Reset $_SERVER to a clean state.
		unset(
			$_SERVER['HTTP_CF_CONNECTING_IP'],
			$_SERVER['HTTP_X_REAL_IP'],
			$_SERVER['HTTP_X_FORWARDED_FOR'],
			$_SERVER['REMOTE_ADDR']
		);
	}

	// =========================================================================
	// Constants
	// =========================================================================

	public function testStatusActiveConstant(): void
	{
		$this->assertSame('active', Session_Manager::STATUS_ACTIVE);
	}

	public function testStatusClosedConstant(): void
	{
		$this->assertSame('closed', Session_Manager::STATUS_CLOSED);
	}

	public function testStatusExpiredConstant(): void
	{
		$this->assertSame('expired', Session_Manager::STATUS_EXPIRED);
	}

	public function testDefaultTtlIs24Hours(): void
	{
		// 86400 seconds = 24 hours
		$this->assertSame(86400, Session_Manager::DEFAULT_TTL);
	}

	public function testMaxHistoryDepthIsFifty(): void
	{
		$this->assertSame(50, Session_Manager::MAX_HISTORY_DEPTH);
	}

	// =========================================================================
	// is_valid_uuid()
	// =========================================================================

	public function testValidUuidV4ReturnsTrue(): void
	{
		$this->assertTrue(Session_Manager::is_valid_uuid('550e8400-e29b-41d4-a716-446655440000'));
	}

	public function testValidUuidV4UppercaseReturnsTrue(): void
	{
		// Regex uses /i flag — uppercase hex is valid.
		$this->assertTrue(Session_Manager::is_valid_uuid('550E8400-E29B-41D4-A716-446655440000'));
	}

	public function testGeneratedUuidIsValid(): void
	{
		// wp_generate_uuid4() is stubbed and produces RFC-4122 v4 format.
		$uuid = wp_generate_uuid4();
		$this->assertTrue(Session_Manager::is_valid_uuid($uuid));
	}

	public function testWrongVersionDigitReturnsFalse(): void
	{
		// Version digit must be '4'; '5' fails.
		$this->assertFalse(Session_Manager::is_valid_uuid('550e8400-e29b-51d4-a716-446655440000'));
	}

	public function testWrongVariantDigitReturnsFalse(): void
	{
		// Variant nibble must be 8, 9, a, or b; '7' fails.
		$this->assertFalse(Session_Manager::is_valid_uuid('550e8400-e29b-41d4-7716-446655440000'));
	}

	public function testEmptyStringReturnsFalse(): void
	{
		$this->assertFalse(Session_Manager::is_valid_uuid(''));
	}

	public function testNonStringReturnsFalse(): void
	{
		$this->assertFalse(Session_Manager::is_valid_uuid(null));
		$this->assertFalse(Session_Manager::is_valid_uuid(42));
		$this->assertFalse(Session_Manager::is_valid_uuid([]));
	}

	public function testTooShortReturnsFalse(): void
	{
		$this->assertFalse(Session_Manager::is_valid_uuid('550e8400-e29b-41d4-a716'));
	}

	public function testTooLongReturnsFalse(): void
	{
		$this->assertFalse(Session_Manager::is_valid_uuid('550e8400-e29b-41d4-a716-4466554400001'));
	}

	// =========================================================================
	// validate_ownership()
	// =========================================================================

	public function testAuthenticatedSessionMatchesUserId(): void
	{
		$session = ['user_id' => 5, 'ip_hash' => '', 'ua_hash' => ''];
		$context = ['user_id' => 5, 'ip_hash' => '', 'ua_hash' => ''];
		$this->assertTrue(Session_Manager::validate_ownership($session, $context));
	}

	public function testAuthenticatedSessionMismatchedUserIdReturnsFalse(): void
	{
		$session = ['user_id' => 5, 'ip_hash' => '', 'ua_hash' => ''];
		$context = ['user_id' => 6, 'ip_hash' => '', 'ua_hash' => ''];
		$this->assertFalse(Session_Manager::validate_ownership($session, $context));
	}

	public function testAnonymousSessionMatchesIpHash(): void
	{
		$hash = hash('sha256', '1.2.3.4');
		$session = ['user_id' => 0, 'ip_hash' => $hash, 'ua_hash' => 'other'];
		$context = ['user_id' => 0, 'ip_hash' => $hash, 'ua_hash' => 'different'];
		$this->assertTrue(Session_Manager::validate_ownership($session, $context));
	}

	public function testAnonymousSessionMatchesUaHash(): void
	{
		$hash = hash('sha256', 'Mozilla/5.0');
		$session = ['user_id' => 0, 'ip_hash' => 'stored-ip', 'ua_hash' => $hash];
		$context = ['user_id' => 0, 'ip_hash' => 'different-ip', 'ua_hash' => $hash];
		$this->assertTrue(Session_Manager::validate_ownership($session, $context));
	}

	public function testAnonymousSessionNeitherHashMatchReturnsFalse(): void
	{
		$session = [
			'user_id' => 0,
			'ip_hash' => hash('sha256', '1.2.3.4'),
			'ua_hash' => hash('sha256', 'Mozilla/5.0'),
		];
		$context = [
			'user_id' => 0,
			'ip_hash' => hash('sha256', '9.9.9.9'),
			'ua_hash' => hash('sha256', 'curl/7.0'),
		];
		$this->assertFalse(Session_Manager::validate_ownership($session, $context));
	}

	public function testAnonymousSessionEmptyHashesReturnsFalse(): void
	{
		// Both hashes empty — neither can match.
		$session = ['user_id' => 0, 'ip_hash' => '', 'ua_hash' => ''];
		$context = ['user_id' => 0, 'ip_hash' => '', 'ua_hash' => ''];
		$this->assertFalse(Session_Manager::validate_ownership($session, $context));
	}

	public function testOwnershipFilterCanOverrideResult(): void
	{
		// Install a filter that always approves.
		add_filter(
			'wpaic_session_ownership_valid',
			static function (bool $valid): bool {
				return true;
			}
		);

		// Session/context that would normally fail.
		$session = [
			'user_id' => 0,
			'ip_hash' => hash('sha256', '1.2.3.4'),
			'ua_hash' => hash('sha256', 'Mozilla/5.0'),
		];
		$context = ['user_id' => 0, 'ip_hash' => hash('sha256', '9.9.9.9'), 'ua_hash' => hash('sha256', 'curl/7.0')];

		$this->assertTrue(Session_Manager::validate_ownership($session, $context));
	}

	// =========================================================================
	// get_client_ip()
	// =========================================================================

	public function testFallsBackToRemoteAddr(): void
	{
		$_SERVER['REMOTE_ADDR'] = '192.168.1.50';
		$this->assertSame('192.168.1.50', Session_Manager::get_client_ip());
	}

	public function testReadsCloudflareHeader(): void
	{
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '10.0.0.1';
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$this->assertSame('10.0.0.1', Session_Manager::get_client_ip());
	}

	public function testReadsXRealIpHeader(): void
	{
		$_SERVER['HTTP_X_REAL_IP'] = '203.0.113.1';
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$this->assertSame('203.0.113.1', Session_Manager::get_client_ip());
	}

	public function testReadsFirstIpFromForwardedFor(): void
	{
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.5, 198.51.100.2, 192.0.2.1';
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$this->assertSame('203.0.113.5', Session_Manager::get_client_ip());
	}

	public function testInvalidProxyHeaderFallsBackToRemoteAddr(): void
	{
		$_SERVER['HTTP_CF_CONNECTING_IP'] = 'not-an-ip';
		$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
		$this->assertSame('192.0.2.10', Session_Manager::get_client_ip());
	}

	public function testNoServerVarsReturnsFallback(): void
	{
		// No server vars set — returns '0.0.0.0'.
		$this->assertSame('0.0.0.0', Session_Manager::get_client_ip());
	}

	public function testIpv6AddressIsAccepted(): void
	{
		$_SERVER['REMOTE_ADDR'] = '::1';
		$this->assertSame('::1', Session_Manager::get_client_ip());
	}
}
