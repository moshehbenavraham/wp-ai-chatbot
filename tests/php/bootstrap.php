<?php

declare(strict_types=1);

// ── WordPress constants required by plugin class files ────────────────────────
// These must be defined before any WPAIC\ class is loaded.

if (! defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__, 2) . '/');
}

if (! defined('WPAIC_PLUGIN_DIR')) {
	define('WPAIC_PLUGIN_DIR', dirname(__DIR__, 2) . '/');
	define('WPAIC_INCLUDES_DIR', WPAIC_PLUGIN_DIR . 'includes/');
	define('WPAIC_VERSION', '1.0.0');
	define('WPAIC_TEXT_DOMAIN', 'wp-ai-chatbot');
	define('WPAIC_REST_NAMESPACE', 'wp-ai-chatbot/v1');
	define('WPAIC_PREFIX', 'wpaic_');
	define('WPAIC_DEBUG', false);
}

// ── Composer autoloader (PHPUnit, smalot/pdfparser, etc.) ────────────────────
require_once __DIR__ . '/../../vendor/autoload.php';

// ── Plugin lib utilities ──────────────────────────────────────────────────────
require_once __DIR__ . '/../../lib/structured-logger.php';

// ── Plugin PSR-4 class autoloader ────────────────────────────────────────────
// Allows test classes to load WPAIC\ namespace classes on first use.
require_once WPAIC_INCLUDES_DIR . 'autoloader.php';
