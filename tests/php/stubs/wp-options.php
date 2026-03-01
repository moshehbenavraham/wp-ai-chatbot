<?php

/**
 * Minimal WP options and database stubs for backup manager tests.
 *
 * Uses Tests\BackupManagerTest::$options as backing store and
 * Tests\BackupManagerTest::$wpdb_rows for simulated DB query results.
 */

if (! function_exists('update_option')) {
	/**
	 * @param string $name
	 * @param mixed  $value
	 * @return bool
	 */
	function update_option(string $name, $value): bool
	{
		$changed = ! isset(Tests\BackupManagerTest::$options[$name])
			|| Tests\BackupManagerTest::$options[$name] !== $value;

		Tests\BackupManagerTest::$options[$name] = $value;

		return $changed;
	}
}

if (! function_exists('current_user_can')) {
	/**
	 * @param string $capability
	 * @return bool
	 */
	function current_user_can(string $capability): bool
	{
		return Tests\BackupManagerTest::$currentUserCan;
	}
}

if (! function_exists('maybe_unserialize')) {
	/**
	 * @param mixed $data
	 * @return mixed
	 */
	function maybe_unserialize($data)
	{
		if (is_string($data) && strpos($data, '{') !== false) {
			$unserialized = @unserialize($data);
			if ($unserialized !== false || $data === 'b:0;') {
				return $unserialized;
			}
		}

		return $data;
	}
}

if (! function_exists('is_wp_error')) {
	/**
	 * @param mixed $thing
	 * @return bool
	 */
	function is_wp_error($thing): bool
	{
		return $thing instanceof \WP_Error;
	}
}
