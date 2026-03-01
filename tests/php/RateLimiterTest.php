<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for lib/rate-limiter.php
 *
 * WordPress functions are stubbed below since we run outside of WP.
 * The transient store is an in-memory array reset between tests.
 */
final class RateLimiterTest extends TestCase
{
	private static bool $loaded = false;

	/** @var array<string, mixed> In-memory transient store. */
	public static array $transients = [];

	public static function setUpBeforeClass(): void
	{
		if (self::$loaded) {
			return;
		}

		// Load global-namespace stubs for WP functions and classes.
		require_once __DIR__ . '/stubs/class-wp-error.php';
		require_once __DIR__ . '/stubs/wp-transients.php';

		if (! defined('ABSPATH')) {
			define('ABSPATH', '/tmp/wp/');
		}

		require_once __DIR__ . '/../../lib/rate-limiter.php';
		self::$loaded = true;
	}

	protected function setUp(): void
	{
		// Reset transient store and SERVER vars between tests.
		self::$transients = [];
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		unset(
			$_SERVER['HTTP_CF_CONNECTING_IP'],
			$_SERVER['HTTP_X_REAL_IP'],
			$_SERVER['HTTP_X_FORWARDED_FOR']
		);
	}

	public function testFirstRequestIsAllowed(): void
	{
		$result = wpaic_check_rate_limit('test', 5, 60);

		$this->assertTrue($result['allowed']);
		$this->assertSame(4, $result['remaining']);
		$this->assertSame(0, $result['retry_after']);
	}

	public function testRequestsWithinLimitAreAllowed(): void
	{
		for ($i = 0; $i < 5; $i++) {
			$result = wpaic_check_rate_limit('test', 5, 60);
		}

		// 5th request should still be allowed (limit is 5).
		$this->assertTrue($result['allowed']);
		$this->assertSame(0, $result['remaining']);
	}

	public function testRequestExceedingLimitIsDenied(): void
	{
		for ($i = 0; $i < 5; $i++) {
			wpaic_check_rate_limit('test', 5, 60);
		}

		// 6th request should be denied.
		$result = wpaic_check_rate_limit('test', 5, 60);

		$this->assertFalse($result['allowed']);
		$this->assertSame(0, $result['remaining']);
		$this->assertGreaterThan(0, $result['retry_after']);
	}

	public function testDifferentNamespacesAreSeparate(): void
	{
		// Exhaust 'chat' namespace.
		for ($i = 0; $i < 3; $i++) {
			wpaic_check_rate_limit('chat', 3, 60);
		}
		$chatResult = wpaic_check_rate_limit('chat', 3, 60);

		// 'health' namespace should still be available.
		$healthResult = wpaic_check_rate_limit('health', 3, 60);

		$this->assertFalse($chatResult['allowed']);
		$this->assertTrue($healthResult['allowed']);
	}

	public function testExpiredWindowResetsCount(): void
	{
		// Simulate a request, then manually expire the window.
		$result = wpaic_check_rate_limit('test', 2, 60);
		$this->assertTrue($result['allowed']);

		// Tamper with the transient to simulate an expired window.
		foreach (self::$transients as $key => &$data) {
			if (is_array($data) && isset($data['window_start'])) {
				$data['window_start'] = time() - 120; // 2 minutes ago
			}
		}
		unset($data);

		// Next request should reset the window and be allowed.
		$result = wpaic_check_rate_limit('test', 2, 60);
		$this->assertTrue($result['allowed']);
		$this->assertSame(1, $result['remaining']);
	}

	public function testGetClientIpFallsBackToRemoteAddr(): void
	{
		$_SERVER['REMOTE_ADDR'] = '192.168.1.100';
		$this->assertSame('192.168.1.100', wpaic_get_client_ip());
	}

	public function testGetClientIpReadsCloudflareHeader(): void
	{
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.50';

		$this->assertSame('203.0.113.50', wpaic_get_client_ip());
	}

	public function testGetClientIpReadsXForwardedForFirstIp(): void
	{
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.10, 10.0.0.2, 10.0.0.3';

		$this->assertSame('198.51.100.10', wpaic_get_client_ip());
	}

	public function testGetClientIpIgnoresInvalidProxyHeader(): void
	{
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';

		$this->assertSame('10.0.0.1', wpaic_get_client_ip());
	}

	public function testRateLimitedPermissionReturnsCallable(): void
	{
		$callback = wpaic_rate_limited_permission('test', 10, 60);
		$this->assertIsCallable($callback);
	}

	public function testRateLimitedPermissionAllowsWithinLimit(): void
	{
		$callback = wpaic_rate_limited_permission('perm_test', 5, 60);
		$result = $callback(null);

		$this->assertTrue($result);
	}

	public function testRateLimitedPermissionDeniesOverLimit(): void
	{
		$callback = wpaic_rate_limited_permission('perm_deny', 2, 60);

		$callback(null); // 1
		$callback(null); // 2
		$result = $callback(null); // 3 — should be denied

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	public function testRateLimitedPermissionCallsInnerCheck(): void
	{
		$innerCalled = false;
		$inner = function ($request) use (&$innerCalled) {
			$innerCalled = true;

			return true;
		};

		$callback = wpaic_rate_limited_permission('inner_test', 10, 60, $inner);
		$callback(null);

		$this->assertTrue($innerCalled);
	}

	public function testRateLimitedPermissionRejectsOnInnerCheckFailure(): void
	{
		$inner = function ($request) {
			return new \WP_Error('no_access', 'Forbidden', ['status' => 403]);
		};

		$callback = wpaic_rate_limited_permission('inner_fail', 10, 60, $inner);
		$result = $callback(null);

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	public function testAddRateLimitHeadersFunctionExists(): void
	{
		$this->assertTrue(
			function_exists('wpaic_add_rate_limit_headers'),
			'wpaic_add_rate_limit_headers should be defined'
		);
	}
}
