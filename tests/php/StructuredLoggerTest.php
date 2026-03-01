<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Structured_Logger;

final class StructuredLoggerTest extends TestCase
{
	private string $logsDir;

	protected function setUp(): void
	{
		$this->logsDir = sys_get_temp_dir() . '/structured-logger-test-' . uniqid();
		mkdir($this->logsDir, 0755, true);
	}

	protected function tearDown(): void
	{
		self::removeDir($this->logsDir);
	}

	private static function removeDir(string $dir): void
	{
		if (! is_dir($dir)) {
			return;
		}
		$items = (array) scandir($dir);
		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}
			$path = $dir . '/' . $item;
			is_dir($path) ? self::removeDir($path) : unlink($path);
		}
		rmdir($dir);
	}

	public function testCaptureErrorWritesJsonFile(): void
	{
		$logger = new Structured_Logger('test', $this->logsDir, true);
		$exception = new \RuntimeException('Test error', 42);

		$path = $logger->capture_error($exception, ['user_id' => 7]);

		$this->assertNotFalse($path, 'capture_error should return a file path');
		$this->assertFileExists($path);

		$json = json_decode((string) file_get_contents($path), true);

		$this->assertIsArray($json);
		$this->assertSame('error', $json['level']);
		$this->assertSame('test', $json['component']);
		$this->assertSame('Test error', $json['msg']);
		$this->assertSame('RuntimeException', $json['error']['type']);
		$this->assertSame(42, $json['error']['code']);
		$this->assertSame(7, $json['context']['user_id']);
	}

	public function testCaptureErrorCreatesDirectoryIfMissing(): void
	{
		$nested = $this->logsDir . '/nested';
		$logger = new Structured_Logger('test', $nested, true);

		$path = $logger->capture_error(new \Exception('dir test'));

		$this->assertDirectoryExists($nested);
		$this->assertNotFalse($path);
	}

	public function testLogMethodCompletes(): void
	{
		$logger = new Structured_Logger('test-component', $this->logsDir, true);

		$logger->info('hello world', ['key' => 'value']);

		$this->assertTrue(true);
	}
}
