<?php

/**
 * PSR-4-compatible custom autoloader for the WPAIC namespace.
 *
 * Mapping rules:
 *   WPAIC\Plugin               → includes/class-plugin.php
 *   WPAIC\{Domain}\{Class}     → includes/{domain}/class-{class}.php
 *   WPAIC\{Dom}\{Sub}\{Class}  → includes/{dom}/{sub}/class-{class}.php
 *
 * File-type prefixes tried in order: class-, interface-, trait-
 * Class name transformation: PascalCase_With_Underscores → kebab-case
 *   e.g. Provider_Registry → class-provider-registry.php
 *        Provider (interface) → interface-provider.php
 *
 * @package WP_AI_Chatbot
 */

if (! defined('ABSPATH')) {
	exit;
}

spl_autoload_register(
	static function (string $class_name): void {
		// Only handle classes in the WPAIC namespace.
		$prefix = 'WPAIC\\';
		if (strpos($class_name, $prefix) !== 0) {
			return;
		}

		// Strip the WPAIC\ prefix to get the relative class path.
		$relative = substr($class_name, strlen($prefix));

		// Split on namespace separator to get directory parts + class name.
		$parts = explode('\\', $relative);

		// The last segment is the unqualified class, interface, or trait name.
		$class = array_pop($parts);

		// Convert PascalCase_With_Underscores → lowercase-kebab-case.
		$file_name = strtolower(str_replace('_', '-', $class));

		// Build the base directory from remaining namespace parts (lowercased).
		if (! empty($parts)) {
			$dir = WPAIC_INCLUDES_DIR . implode('/', array_map('strtolower', $parts)) . '/';
		} else {
			// Root-namespace class (e.g. WPAIC\Plugin) lives directly in includes/.
			$dir = WPAIC_INCLUDES_DIR;
		}

		// Try each file-type prefix in order.
		foreach (['class-', 'interface-', 'trait-'] as $type_prefix) {
			$file = $dir . $type_prefix . $file_name . '.php';
			if (file_exists($file)) {
				require_once $file;

				return;
			}
		}
	}
);
