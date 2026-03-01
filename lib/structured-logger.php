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

	/**
	 * Global structured log function for use throughout the plugin.
	 *
	 * Handles three calling conventions used across the codebase:
	 *
	 *   1. Simple:  wpaic_structured_log( $level, $msg, $context, $subsystem = 'plugin' )
	 *               where $level ∈ {'debug','info','warn','error','critical'}
	 *
	 *   2. Subsystem-first:  wpaic_structured_log( $subsystem, $event, $context )
	 *               where $subsystem is NOT a valid log level (e.g. 'security')
	 *
	 *   3. Audit:   wpaic_structured_log( $subsystem, $level, $event, $context )
	 *               where $subsystem is NOT a valid log level and $context is array
	 *               (e.g. Tool_Executor: wpaic_structured_log('audit','info','tool_executed',[...]))
	 *
	 * All forms write a JSON line to logs/app-{Y-m-d}.log (one line per entry).
	 * The Audit_Logger::query() reader expects each line to have a 'subsystem' key.
	 *
	 * @param  string            $a1  Level or subsystem (see above).
	 * @param  string            $a2  Message/event or level (see above).
	 * @param  array|string      $a3  Context array, or event string (form 3).
	 * @param  array|string      $a4  Subsystem, or context array (form 3).
	 * @return void
	 */
	function wpaic_structured_log(string $a1, string $a2, $a3 = [], $a4 = ''): void
	{
		static $valid_levels = ['debug', 'info', 'warn', 'error', 'critical'];

		if (is_array($a3)) {
			// Forms 1 and 2: third arg is an array (context).
			if (in_array($a1, $valid_levels, true)) {
				// Form 1: (level, message, context, subsystem?)
				$level = $a1;
				$message = $a2;
				$context = $a3;
				$subsystem = is_string($a4) && '' !== $a4 ? $a4 : 'plugin';
			} else {
				// Form 2: (subsystem, event, context) — level implicitly 'info'
				$subsystem = $a1;
				$level = 'info';
				$message = $a2;
				$context = $a3;
			}
		} else {
			// Form 3: (subsystem, level, event, context_array)
			$subsystem = $a1;
			$level = in_array($a2, $valid_levels, true) ? $a2 : 'info';
			$message = is_string($a3) ? $a3 : '';
			$context = is_array($a4) ? $a4 : [];
		}

		// Build a flat JSON log entry that Audit_Logger::query() can parse.
		// Context is merged at the top level so event_type / other fields are
		// directly accessible as JSON keys (audit entries pre-populate event_type).
		$entry = array_merge(
			[
				'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
				'level' => $level,
				'subsystem' => $subsystem,
				'msg' => $message,
			],
			$context
		);

		$json_line = wp_json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		if (false === $json_line) {
			return;
		}

		// Write to daily rotating log file: logs/app-{Y-m-d}.log
		$log_dir = defined('WPAIC_PLUGIN_DIR') ? rtrim(WPAIC_PLUGIN_DIR, '/') . '/logs' : '';

		if ('' !== $log_dir) {
			if (! is_dir($log_dir)) {
				wp_mkdir_p($log_dir);
			}
			$log_file = $log_dir . '/app-' . gmdate('Y-m-d') . '.log';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents($log_file, $json_line . "\n", FILE_APPEND | LOCK_EX);
		} elseif (defined('WP_DEBUG') && WP_DEBUG) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log('[WPAIC][' . strtoupper($level) . '][' . $subsystem . '] ' . $message);
		}
	}

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
