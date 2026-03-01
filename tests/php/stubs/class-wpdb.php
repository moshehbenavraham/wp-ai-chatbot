<?php

/**
 * Minimal wpdb stub for unit tests outside WordPress.
 *
 * The get_results method returns Tests\BackupManagerTest::$wpdb_rows.
 */

if (! defined('ARRAY_A')) {
	define('ARRAY_A', 'ARRAY_A');
}

class wpdb
{
	/** @var string */
	public string $options = 'wp_options';

	/** @var string */
	public string $last_error = '';

	/**
	 * @param string $query
	 * @param mixed  ...$args
	 * @return string
	 */
	public function prepare(string $query, ...$args): string
	{
		$i = 0;

		return preg_replace_callback('/%[sd]/', function () use ($args, &$i) {
			return "'" . ($args[$i++] ?? '') . "'";
		}, $query);
	}

	/**
	 * @param string $text
	 * @return string
	 */
	public function esc_like(string $text): string
	{
		return addcslashes($text, '_%\\');
	}

	/**
	 * @param string $query
	 * @param string $output
	 * @return array<int, array<string, string>>|null
	 */
	public function get_results(string $query, string $output = 'OBJECT')
	{
		return Tests\BackupManagerTest::$wpdb_rows;
	}

	/**
	 * @param string $query
	 * @return string|int|null
	 */
	public function get_var(string $query)
	{
		return '1';
	}
}
