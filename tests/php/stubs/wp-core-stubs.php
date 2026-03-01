<?php

/**
 * Core WordPress function stubs for WPAIC OOP class unit tests.
 *
 * All definitions are guarded with function_exists() checks so they coexist
 * safely with stubs loaded by other test classes in the same PHPUnit process.
 *
 * Option storage uses WpTestHelpers::$options and (when available) the
 * BackupManagerTest::$options store, so that get_option/update_option
 * round-trips are consistent regardless of which stub defined update_option first.
 *
 * Usage in test classes:
 *   public static function setUpBeforeClass(): void {
 *       require_once __DIR__ . '/stubs/wp-core-stubs.php';
 *       // load class under test via autoloader or require_once
 *   }
 *   protected function setUp(): void {
 *       WpTestHelpers::reset();
 *   }
 */

if (! class_exists('WpTestHelpers')) {
	/**
	 * Central store for test-managed WordPress state.
	 * Use reset() in setUp() to clear state between test cases.
	 */
	class WpTestHelpers
	{
		/** @var array<string, mixed> Option backing store. */
		public static array $options = [];

		/** @var array<string, array<int, callable[]>> Hook registry [tag][priority][]. */
		public static array $hooks = [];

		/**
		 * Reset all test state.
		 * Also clears BackupManagerTest::$options when available so that
		 * the update_option stub (which writes there) is also reset.
		 */
		public static function reset(): void
		{
			self::$options = [];
			self::$hooks = [];

			if (class_exists('Tests\\BackupManagerTest')) {
				Tests\BackupManagerTest::$options = [];
			}
		}

		/**
		 * Pre-seed an option into whichever store update_option will write to.
		 *
		 * @param string $key
		 * @param mixed  $value
		 */
		public static function seed_option(string $key, $value): void
		{
			self::$options[$key] = $value;

			// Mirror into BackupManagerTest store when update_option points there.
			if (class_exists('Tests\\BackupManagerTest')) {
				Tests\BackupManagerTest::$options[$key] = $value;
			}
		}
	}
}

// ── Options ───────────────────────────────────────────────────────────────────

if (! function_exists('get_option')) {
	/**
	 * @param string $option
	 * @param mixed  $default
	 * @return mixed
	 */
	function get_option(string $option, $default = false)
	{
		// Prefer BackupManagerTest store when it is available and contains the key,
		// keeping get/update_option in sync within the same PHPUnit process.
		if (class_exists('Tests\\BackupManagerTest')
			&& array_key_exists($option, Tests\BackupManagerTest::$options)
		) {
			return Tests\BackupManagerTest::$options[$option];
		}

		if (array_key_exists($option, WpTestHelpers::$options)) {
			return WpTestHelpers::$options[$option];
		}

		return $default;
	}
}

if (! function_exists('update_option')) {
	/**
	 * @param string    $option
	 * @param mixed     $value
	 * @param bool|null $autoload
	 * @return bool
	 */
	function update_option(string $option, $value, $autoload = null): bool
	{
		WpTestHelpers::$options[$option] = $value;

		return true;
	}
}

if (! function_exists('delete_option')) {
	/**
	 * @param string $option
	 * @return bool
	 */
	function delete_option(string $option): bool
	{
		$existed = array_key_exists($option, WpTestHelpers::$options);
		unset(WpTestHelpers::$options[$option]);

		return $existed;
	}
}

// ── Filter / action hook system ───────────────────────────────────────────────

if (! function_exists('apply_filters')) {
	/**
	 * @param string $tag
	 * @param mixed  $value
	 * @param mixed  ...$args
	 * @return mixed
	 */
	function apply_filters(string $tag, $value, ...$args)
	{
		if (empty(WpTestHelpers::$hooks[$tag])) {
			return $value;
		}

		$by_priority = WpTestHelpers::$hooks[$tag];
		ksort($by_priority);

		foreach ($by_priority as $callbacks) {
			foreach ($callbacks as $cb) {
				$value = $cb($value, ...$args);
			}
		}

		return $value;
	}
}

if (! function_exists('add_filter')) {
	/**
	 * @param string   $tag
	 * @param callable $callback
	 * @param int      $priority
	 * @param int      $accepted_args
	 * @return bool
	 */
	function add_filter(string $tag, callable $callback, int $priority = 10, int $accepted_args = 1): bool
	{
		WpTestHelpers::$hooks[$tag][$priority][] = $callback;

		return true;
	}
}

if (! function_exists('add_action')) {
	/**
	 * @param string   $tag
	 * @param callable $callback
	 * @param int      $priority
	 * @param int      $accepted_args
	 * @return bool
	 */
	function add_action(string $tag, callable $callback, int $priority = 10, int $accepted_args = 1): bool
	{
		return add_filter($tag, $callback, $priority, $accepted_args);
	}
}

if (! function_exists('do_action')) {
	/**
	 * @param string $tag
	 * @param mixed  ...$args
	 */
	function do_action(string $tag, ...$args): void
	{
		if (empty(WpTestHelpers::$hooks[$tag])) {
			return;
		}

		$by_priority = WpTestHelpers::$hooks[$tag];
		ksort($by_priority);

		foreach ($by_priority as $callbacks) {
			foreach ($callbacks as $cb) {
				$cb(...$args);
			}
		}
	}
}

// ── Text / sanitization ───────────────────────────────────────────────────────

if (! function_exists('absint')) {
	/**
	 * Convert a value to a non-negative integer (WordPress absint).
	 *
	 * @param  mixed $value
	 * @return int
	 */
	function absint($value): int
	{
		return abs((int) $value);
	}
}

if (! function_exists('sanitize_text_field')) {
	/**
	 * @param mixed $str
	 * @return string
	 */
	function sanitize_text_field($str): string
	{
		$str = (string) $str;
		$str = strip_tags($str);
		$str = (string) preg_replace('/[\r\n\t ]+/', ' ', $str);

		return trim($str);
	}
}

if (! function_exists('sanitize_key')) {
	/**
	 * @param mixed $key
	 * @return string
	 */
	function sanitize_key($key): string
	{
		$key = strtolower((string) $key);

		return (string) preg_replace('/[^a-z0-9_\-]/', '', $key);
	}
}

if (! function_exists('sanitize_textarea_field')) {
	/**
	 * @param mixed $str
	 * @return string
	 */
	function sanitize_textarea_field($str): string
	{
		$str = (string) $str;
		$str = strip_tags($str);
		$str = str_replace("\r", "\n", $str);
		$str = (string) preg_replace('/\n{3,}/', "\n\n", $str);

		return trim($str);
	}
}

if (! function_exists('sanitize_file_name')) {
	/**
	 * @param string $name
	 * @return string
	 */
	function sanitize_file_name(string $name): string
	{
		// Remove characters that are illegal in filenames.
		$name = (string) preg_replace('/[\/\\\\:*?"<>|]/', '', $name);
		$name = (string) preg_replace('/\.{2,}/', '.', $name);

		return trim($name, '. ');
	}
}

if (! function_exists('wp_strip_all_tags')) {
	/**
	 * @param string $text
	 * @param bool   $remove_breaks
	 * @return string
	 */
	function wp_strip_all_tags(string $text, bool $remove_breaks = false): string
	{
		$text = strip_tags($text);

		if ($remove_breaks) {
			$text = (string) preg_replace('/[\r\n\t ]+/', ' ', $text);
		}

		return trim($text);
	}
}

if (! function_exists('esc_html')) {
	/**
	 * @param string $text
	 * @return string
	 */
	function esc_html(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}

if (! function_exists('esc_url')) {
	/**
	 * Minimal esc_url: HTML-encodes special characters in URLs.
	 *
	 * @param string $url
	 * @return string
	 */
	function esc_url(string $url): string
	{
		// Strip dangerous protocols.
		$url = trim($url);

		if ('' === $url) {
			return '';
		}

		$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

		// Reject non-safe protocols.
		$safe_schemes = ['http', 'https', 'mailto', 'tel', ''];

		if ('' !== $scheme && ! in_array($scheme, $safe_schemes, true)) {
			return '';
		}

		return htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}

if (! function_exists('esc_url_raw')) {
	/**
	 * @param string $url
	 * @return string
	 */
	function esc_url_raw(string $url): string
	{
		$url = trim($url);
		$result = filter_var($url, FILTER_SANITIZE_URL);

		return is_string($result) ? $result : '';
	}
}

if (! function_exists('esc_html__')) {
	/**
	 * @param string $text
	 * @param string $domain
	 * @return string
	 */
	function esc_html__(string $text, string $domain = 'default'): string
	{
		return esc_html($text);
	}
}

if (! function_exists('__')) {
	/**
	 * @param string $text
	 * @param string $domain
	 * @return string
	 */
	function __(string $text, string $domain = 'default'): string
	{
		return $text;
	}
}

if (! function_exists('_doing_it_wrong')) {
	/**
	 * @param string $function
	 * @param string $message
	 * @param string $version
	 */
	function _doing_it_wrong(string $function, string $message, string $version): void
	{
		// No-op in test context.
	}
}

// ── HTML ──────────────────────────────────────────────────────────────────────

if (! function_exists('wp_kses')) {
	/**
	 * Minimal wp_kses: strips tags not in the allowlist.
	 *
	 * Does not strip attributes (simplified for test use).
	 *
	 * @param string               $data
	 * @param array<string, mixed> $allowed_html
	 * @return string
	 */
	function wp_kses(string $data, array $allowed_html): string
	{
		if (empty($allowed_html)) {
			return strip_tags($data);
		}

		$allowed_str = implode('', array_map(
			static function (string $tag): string {
				return '<' . $tag . '>';
			},
			array_keys($allowed_html)
		));

		return strip_tags($data, $allowed_str);
	}
}

// ── URL / parsing ─────────────────────────────────────────────────────────────

if (! function_exists('wp_parse_url')) {
	/**
	 * @param string   $url
	 * @param int      $component
	 * @return mixed
	 */
	function wp_parse_url(string $url, int $component = -1)
	{
		if (-1 === $component) {
			$result = parse_url($url);

			return is_array($result) ? $result : null;
		}

		$result = parse_url($url, $component);

		return (false === $result) ? null : $result;
	}
}

// ── JSON ──────────────────────────────────────────────────────────────────────

if (! function_exists('wp_json_encode')) {
	/**
	 * @param mixed $data
	 * @param int   $options
	 * @param int   $depth
	 * @return string|false
	 */
	function wp_json_encode($data, int $options = 0, int $depth = 512)
	{
		return json_encode($data, $options, $depth);
	}
}

// ── UUID ──────────────────────────────────────────────────────────────────────

if (! function_exists('wp_generate_uuid4')) {
	/**
	 * Generate a random UUID v4 string.
	 *
	 * @return string  e.g. "550e8400-e29b-41d4-a716-446655440000"
	 */
	function wp_generate_uuid4(): string
	{
		$bytes = random_bytes(16);
		$bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
		$bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
		$hex = bin2hex($bytes);

		return vsprintf(
			'%s%s-%s-%s-%s-%s%s%s',
			str_split($hex, 4)
		);
	}
}

// ── Auth / user ───────────────────────────────────────────────────────────────

if (! function_exists('wp_salt')) {
	/**
	 * Return a deterministic test salt (never used for real encryption in tests).
	 *
	 * @param string $scheme
	 * @return string
	 */
	function wp_salt(string $scheme = 'auth'): string
	{
		return 'test_salt_deterministic_for_phpunit_' . $scheme;
	}
}

if (! function_exists('is_admin')) {
	/**
	 * @return bool
	 */
	function is_admin(): bool
	{
		return false;
	}
}

if (! function_exists('get_current_user_id')) {
	/**
	 * @return int
	 */
	function get_current_user_id(): int
	{
		return 0;
	}
}

if (! function_exists('wp_unslash')) {
	/**
	 * Strip slashes from a string or array of strings.
	 *
	 * @param  mixed $value
	 * @return mixed
	 */
	function wp_unslash($value)
	{
		if (is_string($value)) {
			return stripslashes($value);
		}

		if (is_array($value)) {
			return array_map('wp_unslash', $value);
		}

		return $value;
	}
}
