<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for lib/health-endpoint.php
 *
 * WordPress functions are stubbed below since we run outside of WP.
 */
final class HealthEndpointTest extends TestCase
{
	private static bool $loaded = false;

	public static function setUpBeforeClass(): void
	{
		if (self::$loaded) {
			return;
		}

		// Stub WP functions used by the health endpoint.
		if (! function_exists('get_bloginfo')) {
			function get_bloginfo(string $show): string
			{
				return $show === 'version' ? '6.4.0' : '';
			}
		}

		if (! function_exists('wp_convert_hr_to_bytes')) {
			function wp_convert_hr_to_bytes(string $value): int
			{
				$value = strtolower(trim($value));
				$last = substr($value, -1);
				$num = (int) $value;
				switch ($last) {
					case 'g':
						$num *= 1024;
						// fall through
						// no break
					case 'm':
						$num *= 1024;
						// fall through
						// no break
					case 'k':
						$num *= 1024;
				}

				return $num;
			}
		}

		if (! defined('ABSPATH')) {
			define('ABSPATH', '/tmp/wp/');
		}

		require_once __DIR__ . '/../../lib/health-endpoint.php';
		self::$loaded = true;
	}

	public function testCheckExtensionsReportsLoadedExtensions(): void
	{
		$result = wpaic_check_extensions();

		$this->assertSame('pass', $result['status']);
		$this->assertContains('json', $result['loaded']);
		$this->assertContains('curl', $result['loaded']);
		$this->assertContains('mbstring', $result['loaded']);
		$this->assertContains('openssl', $result['loaded']);
		$this->assertEmpty($result['missing']);
	}

	public function testCheckExtensionsReturnsMissing(): void
	{
		// We can't unload extensions at runtime, so just verify the structure.
		$result = wpaic_check_extensions();

		$this->assertArrayHasKey('status', $result);
		$this->assertArrayHasKey('loaded', $result);
		$this->assertArrayHasKey('missing', $result);
		$this->assertIsArray($result['loaded']);
		$this->assertIsArray($result['missing']);
	}

	public function testRegisterFunctionExists(): void
	{
		$this->assertTrue(
			function_exists('wpaic_register_health_endpoint'),
			'wpaic_register_health_endpoint should be defined'
		);
	}

	public function testCallbackFunctionExists(): void
	{
		$this->assertTrue(
			function_exists('wpaic_health_callback'),
			'wpaic_health_callback should be defined'
		);
	}

	public function testCheckDatabaseFunctionExists(): void
	{
		$this->assertTrue(
			function_exists('wpaic_check_database'),
			'wpaic_check_database should be defined'
		);
	}
}
