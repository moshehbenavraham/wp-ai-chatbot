<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for lib/backup-manager.php
 *
 * WordPress functions and classes are stubbed in tests/php/stubs/.
 */
final class BackupManagerTest extends TestCase
{
	private static bool $loaded = false;

	/** @var array<string, mixed> In-memory option store. */
	public static array $options = [];

	/** @var array<int, array<string, string>>|null Simulated wpdb rows. */
	public static ?array $wpdb_rows = null;

	/** @var bool Simulated current_user_can result. */
	public static bool $currentUserCan = true;

	public static function setUpBeforeClass(): void
	{
		if (self::$loaded) {
			return;
		}

		// Load stubs (order matters: classes before functions that reference them).
		require_once __DIR__ . '/stubs/class-wp-error.php';
		require_once __DIR__ . '/stubs/class-wp-rest-response.php';
		require_once __DIR__ . '/stubs/class-wp-rest-request.php';
		require_once __DIR__ . '/stubs/class-wpdb.php';
		require_once __DIR__ . '/stubs/wp-options.php';

		if (! defined('ABSPATH')) {
			define('ABSPATH', '/tmp/wp/');
		}

		// Set up global $wpdb stub.
		global $wpdb;
		$wpdb = new \wpdb();

		require_once __DIR__ . '/../../lib/backup-manager.php';
		self::$loaded = true;
	}

	protected function setUp(): void
	{
		self::$options = [];
		self::$wpdb_rows = [];
		self::$currentUserCan = true;
	}

	// ── Registration ──

	public function testRegisterFunctionExists(): void
	{
		$this->assertTrue(
			function_exists('wpaic_register_backup_endpoints'),
			'wpaic_register_backup_endpoints should be defined'
		);
	}

	// ── Permission Check ──

	public function testPermissionCheckAllowsAdmin(): void
	{
		self::$currentUserCan = true;
		$result = wpaic_backup_permission_check();
		$this->assertTrue($result);
	}

	public function testPermissionCheckDeniesNonAdmin(): void
	{
		self::$currentUserCan = false;
		$result = wpaic_backup_permission_check();
		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('rest_forbidden', $result->get_error_code());
	}

	// ── Export ──

	public function testCollectExportDataReturnsEnvelope(): void
	{
		self::$wpdb_rows = [];

		$data = wpaic_collect_export_data();

		$this->assertArrayHasKey('version', $data);
		$this->assertArrayHasKey('plugin', $data);
		$this->assertArrayHasKey('exported_at', $data);
		$this->assertArrayHasKey('options', $data);
		$this->assertSame(WPAIC_BACKUP_VERSION, $data['version']);
		$this->assertSame('wp-ai-chatbot', $data['plugin']);
	}

	public function testCollectExportDataIncludesOptions(): void
	{
		self::$wpdb_rows = [
			['option_name' => 'wpaic_api_key', 'option_value' => 'sk-test-123'],
			['option_name' => 'wpaic_model', 'option_value' => 'gpt-4'],
		];

		$data = wpaic_collect_export_data();

		$this->assertCount(2, $data['options']);
		$this->assertSame('sk-test-123', $data['options']['wpaic_api_key']);
		$this->assertSame('gpt-4', $data['options']['wpaic_model']);
	}

	public function testCollectExportDataHandlesNullRows(): void
	{
		self::$wpdb_rows = null;

		$data = wpaic_collect_export_data();

		$this->assertIsArray($data['options']);
		$this->assertEmpty($data['options']);
	}

	public function testExportCallbackReturns200(): void
	{
		self::$wpdb_rows = [];
		$request = new \WP_REST_Request();

		$response = wpaic_backup_export_callback($request);

		$this->assertInstanceOf(\WP_REST_Response::class, $response);
		$this->assertSame(200, $response->get_status());
	}

	// ── Validation ──

	public function testValidateRejectsPayloadWithoutVersion(): void
	{
		$result = wpaic_validate_backup_payload([
			'plugin' => 'wp-ai-chatbot',
			'options' => [],
		]);

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	public function testValidateRejectsWrongPlugin(): void
	{
		$result = wpaic_validate_backup_payload([
			'version' => '1.0',
			'plugin' => 'some-other-plugin',
			'options' => [],
		]);

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	public function testValidateRejectsPayloadWithoutOptions(): void
	{
		$result = wpaic_validate_backup_payload([
			'version' => '1.0',
			'plugin' => 'wp-ai-chatbot',
		]);

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	public function testValidateRejectsOptionWithBadPrefix(): void
	{
		$result = wpaic_validate_backup_payload([
			'version' => '1.0',
			'plugin' => 'wp-ai-chatbot',
			'options' => ['foreign_option' => 'bad'],
		]);

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	public function testValidateAcceptsValidPayload(): void
	{
		$result = wpaic_validate_backup_payload([
			'version' => '1.0',
			'plugin' => 'wp-ai-chatbot',
			'options' => ['wpaic_model' => 'gpt-4'],
		]);

		$this->assertTrue($result);
	}

	public function testValidateAcceptsEmptyOptions(): void
	{
		$result = wpaic_validate_backup_payload([
			'version' => '1.0',
			'plugin' => 'wp-ai-chatbot',
			'options' => [],
		]);

		$this->assertTrue($result);
	}

	// ── Import ──

	public function testApplyImportDataWritesOptions(): void
	{
		$payload = [
			'version' => '1.0',
			'plugin' => 'wp-ai-chatbot',
			'options' => [
				'wpaic_api_key' => 'sk-imported',
				'wpaic_model' => 'claude-3',
			],
		];

		$result = wpaic_apply_import_data($payload);

		$this->assertSame('complete', $result['status']);
		$this->assertSame(2, $result['imported']);
		$this->assertSame('sk-imported', self::$options['wpaic_api_key']);
		$this->assertSame('claude-3', self::$options['wpaic_model']);
	}

	public function testApplyImportDataCountsUnchangedAsSkipped(): void
	{
		// Pre-set an option with the same value.
		self::$options['wpaic_model'] = 'gpt-4';

		$payload = [
			'version' => '1.0',
			'plugin' => 'wp-ai-chatbot',
			'options' => ['wpaic_model' => 'gpt-4'],
		];

		$result = wpaic_apply_import_data($payload);

		$this->assertSame(0, $result['imported']);
		$this->assertSame(1, $result['skipped']);
	}

	public function testImportCallbackRejectsEmptyBody(): void
	{
		$request = new \WP_REST_Request(null);

		$result = wpaic_backup_import_callback($request);

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	public function testImportCallbackRejectsInvalidPayload(): void
	{
		$request = new \WP_REST_Request([
			'plugin' => 'wrong',
			'options' => [],
		]);

		$result = wpaic_backup_import_callback($request);

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	public function testImportCallbackSucceeds(): void
	{
		$request = new \WP_REST_Request([
			'version' => '1.0',
			'plugin' => 'wp-ai-chatbot',
			'options' => ['wpaic_setting' => 'value'],
		]);

		$response = wpaic_backup_import_callback($request);

		$this->assertInstanceOf(\WP_REST_Response::class, $response);
		$this->assertSame(200, $response->get_status());
		$data = $response->get_data();
		$this->assertSame('complete', $data['status']);
	}
}
