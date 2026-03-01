<?php

/**
 * Structured Logger — AI-friendly JSON logging with error capture.
 *
 * Usage:
 *   require_once __DIR__ . '/../lib/structured-logger.php';
 *   $logger = new Structured_Logger( 'my-component' );
 *   $logger->info( 'Processing started', [ 'batch_size' => 50 ] );
 *   $logger->capture_error( new \Exception( 'Something failed' ), [ 'user_id' => 42 ] );
 */

if (! defined('STRUCTURED_LOGGER_LOADED')) {
	define('STRUCTURED_LOGGER_LOADED', true);

	class Structured_Logger
	{
		/** @var string */
		private $component;

		/** @var string */
		private $logs_dir;

		/** @var bool When true, suppress stderr output (useful in tests). */
		private $quiet;

		/** @var string[] Valid log levels. */
		private static $levels = ['debug', 'info', 'warn', 'error'];

		/**
		 * @param string      $component  Logical name for the emitting component.
		 * @param string|null $logs_dir   Absolute path to logs directory (defaults to project root /logs).
		 * @param bool        $quiet      Suppress stderr output (file capture still works).
		 */
		public function __construct(string $component = 'app', ?string $logs_dir = null, bool $quiet = false)
		{
			$this->component = $component;
			$this->logs_dir = $logs_dir ?? dirname(__DIR__) . '/logs';
			$this->quiet = $quiet;
		}

		/* ── convenience methods ────────────────────────────────── */

		public function debug(string $msg, array $context = []): void
		{
			$this->log('debug', $msg, $context);
		}

		public function info(string $msg, array $context = []): void
		{
			$this->log('info', $msg, $context);
		}

		public function warn(string $msg, array $context = []): void
		{
			$this->log('warn', $msg, $context);
		}

		public function error(string $msg, array $context = []): void
		{
			$this->log('error', $msg, $context);
		}

		/* ── core log method ────────────────────────────────────── */

		/**
		 * Write a structured JSON log line to stdout (or a log file).
		 *
		 * @param string $level   One of debug, info, warn, error.
		 * @param string $msg     Human-readable message.
		 * @param array  $context Arbitrary key-value context.
		 */
		public function log(string $level, string $msg, array $context = []): void
		{
			if (! in_array($level, self::$levels, true)) {
				$level = 'info';
			}

			$entry = [
				'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
				'level' => $level,
				'component' => $this->component,
				'msg' => $msg,
			];

			if ($context) {
				$entry['context'] = $context;
			}

			$json = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

			// In CLI / dev mode write to stderr so it doesn't interfere with stdout data.
			if (PHP_SAPI === 'cli' && ! $this->quiet) {
				fwrite(STDERR, $json . "\n");
			}
		}

		/* ── error capture ──────────────────────────────────────── */

		/**
		 * Capture a Throwable and write it to logs/last_error_<timestamp>.json.
		 *
		 * @param \Throwable $exception The exception or error to capture.
		 * @param array      $context   Extra context to attach.
		 * @return string|false          Path to the written file, or false on failure.
		 */
		public function capture_error(\Throwable $exception, array $context = [])
		{
			$timestamp = gmdate('Y-m-d\TH:i:s.v\Z');
			$safe_ts = str_replace(':', '-', $timestamp);

			$payload = [
				'timestamp' => $timestamp,
				'level' => 'error',
				'component' => $this->component,
				'msg' => $exception->getMessage(),
				'error' => [
					'type' => get_class($exception),
					'message' => $exception->getMessage(),
					'code' => $exception->getCode(),
					'file' => $exception->getFile(),
					'line' => $exception->getLine(),
					'stack' => $exception->getTraceAsString(),
				],
				'context' => $context,
			];

			$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			$path = $this->logs_dir . '/last_error_' . $safe_ts . '.json';

			if (! is_dir($this->logs_dir)) {
				mkdir($this->logs_dir, 0755, true);
			}

			$written = file_put_contents($path, $json . "\n");

			// Also log to the normal channel.
			$this->error('Error captured: ' . $exception->getMessage(), [
				'error_type' => get_class($exception),
				'error_file' => $path,
			]);

			return $written !== false ? $path : false;
		}
	}
}
