<?php

/**
 * Minimal WP transient stubs for unit tests outside WordPress.
 *
 * Uses Tests\RateLimiterTest::$transients as the backing store
 * so tests can inspect and manipulate transient state.
 */

if (! function_exists('get_transient')) {
	/**
	 * @param string $key
	 * @return mixed
	 */
	function get_transient(string $key)
	{
		return Tests\RateLimiterTest::$transients[$key] ?? false;
	}
}

if (! function_exists('set_transient')) {
	/**
	 * @param string $key
	 * @param mixed  $value
	 * @param int    $expiration
	 * @return bool
	 */
	function set_transient(string $key, $value, int $expiration = 0): bool
	{
		Tests\RateLimiterTest::$transients[$key] = $value;

		return true;
	}
}
