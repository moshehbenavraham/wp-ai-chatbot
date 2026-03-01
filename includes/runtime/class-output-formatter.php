<?php

/**
 * Output Formatter — sanitize and render AI provider responses for the browser.
 *
 * Processing pipeline (in order):
 *   1. Extract suggestion chips (JSON marker strip)
 *   2. Convert lightweight Markdown to safe HTML
 *   3. Apply wp_kses() allowlist
 *   4. Post-process links (scheme validation, rel/target enforcement)
 *
 * Security: defense-in-depth against stored/reflected/DOM-based XSS.
 * See Phase-02 spec: input_output_safety.md for full allowlist rationale.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Runtime;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Output_Formatter
 *
 * @since 1.0.0
 * @package WPAIC\Runtime
 */
class Output_Formatter
{
	/**
	 * Allowed HTML tags and attributes for wp_kses() output pass.
	 * Mirrors the client-side DOMParser allowlist in chatbot.js.
	 *
	 * @var array<string, array<string, bool|string[]>>
	 */
	private static $kses_allowlist = [
		'a' => [
			'href' => true,
			'title' => true,
			'target' => true,
			'rel' => true,
		],
		'strong' => [],
		'em' => [],
		'b' => [],
		'i' => [],
		'br' => [],
		'sup' => [],
		'sub' => [],
		'del' => [],
		'p' => [],
		'blockquote' => [],
		'hr' => [],
		'ul' => [],
		'ol' => ['start' => true],
		'li' => [],
		'pre' => [],
		'code' => [],
		'h3' => [],
		'h4' => [],
		'h5' => [],
		'h6' => [],
		'table' => [],
		'thead' => [],
		'tbody' => [],
		'tr' => [],
		'th' => ['scope' => true],
		'td' => ['colspan' => true, 'rowspan' => true],
	];

	/** Allowed URL schemes for anchor hrefs. */
	private static $allowed_schemes = ['https', 'http', 'mailto', 'tel'];

	// =========================================================================
	// Public API
	// =========================================================================

	/**
	 * Format a raw AI response for safe browser rendering.
	 *
	 * @param  string $raw        Raw text from provider (may include Markdown).
	 * @param  bool   $to_html    Convert Markdown to HTML (default true).
	 * @return array {
	 *     @type string   $html        Safe HTML for browser injection.
	 *     @type string[] $suggestions Extracted suggestion chip labels.
	 * }
	 */
	public static function format(string $raw, bool $to_html = true): array
	{
		// Step 1: extract and strip suggestion markers.
		[$suggestions, $text] = self::extract_suggestions($raw);

		// Step 2: trim whitespace.
		$text = trim($text);

		// Step 3: convert Markdown to HTML (optional — disabled for plain-text bots).
		if ($to_html) {
			$text = self::convert_markdown($text);
		} else {
			// Still escape to prevent raw HTML injection.
			$text = nl2br(esc_html($text));
		}

		// Step 4: apply wp_kses allowlist.
		$html = wp_kses($text, self::get_kses_allowlist());

		// Step 5: post-process links for safety.
		$html = self::sanitize_links($html);

		/**
		 * Filter the final formatted HTML before sending to the client.
		 *
		 * @since 1.0.0
		 * @param string   $html        Sanitized HTML.
		 * @param string   $raw         Original raw response.
		 * @param string[] $suggestions Extracted suggestion labels.
		 */
		$html = (string) apply_filters('wpaic_sanitize_response', $html, $raw, $suggestions);

		return [
			'html' => $html,
			'suggestions' => $suggestions,
		];
	}

	/**
	 * Get the wp_kses allowlist (filterable by extensions).
	 *
	 * @return array
	 */
	public static function get_kses_allowlist(): array
	{
		/**
		 * Filter the allowed HTML tags/attributes for bot responses.
		 *
		 * @since 1.0.0
		 * @param array $allowlist Tag => attributes map.
		 */
		return (array) apply_filters('wpaic_kses_allowlist', self::$kses_allowlist);
	}

	// =========================================================================
	// Suggestion extraction
	// =========================================================================

	/**
	 * Extract suggestion chips embedded by the AI using a JSON marker.
	 *
	 * Expected format (anywhere in the response, typically at the end):
	 *   <!--SUGGESTIONS:["Chip 1","Chip 2","Chip 3"]-->
	 *
	 * @param  string $text Raw response text.
	 * @return array {[$suggestions, $stripped_text]}
	 */
	public static function extract_suggestions(string $text): array
	{
		$suggestions = [];

		$pattern = '/<!--SUGGESTIONS:(\[.*?\])-->/s';
		if (preg_match($pattern, $text, $matches)) {
			$decoded = json_decode($matches[1], true);
			if (is_array($decoded)) {
				$suggestions = array_filter(array_map('sanitize_text_field', $decoded));
				$suggestions = array_values($suggestions);
			}
			// Strip the marker from the response text.
			$text = preg_replace($pattern, '', $text);
		}

		return [$suggestions, (string) $text];
	}

	// =========================================================================
	// Markdown → HTML
	// =========================================================================

	/**
	 * Convert a lightweight Markdown subset to HTML.
	 *
	 * Supported constructs (in processing order):
	 *   - Fenced code blocks: ```lang\n...\n```  → <pre><code>...</code></pre>
	 *   - Inline code: `code`                    → <code>...</code>
	 *   - ATX headings: ### Heading              → <h3>...</h3>
	 *   - Bold: **text** or __text__             → <strong>...</strong>
	 *   - Italic: *text* or _text_               → <em>...</em>
	 *   - Strikethrough: ~~text~~                → <del>...</del>
	 *   - Unordered lists: - item or * item      → <ul><li>...</li></ul>
	 *   - Ordered lists: 1. item                 → <ol><li>...</li></ul>
	 *   - Blockquotes: > text                    → <blockquote>...</blockquote>
	 *   - Horizontal rules: --- or ***           → <hr>
	 *   - Links: [text](url)                     → <a href="url">text</a>
	 *   - Paragraphs: blank-line separation      → <p>...</p>
	 *
	 * Security: raw HTML is escaped BEFORE Markdown conversion so the AI cannot
	 * inject tags via Markdown. The esc_html() call here is intentional.
	 *
	 * @param  string $text Raw Markdown text.
	 * @return string HTML string (not yet kses-filtered).
	 */
	public static function convert_markdown(string $text): string
	{
		// Escape all HTML entities first to prevent tag injection via Markdown.
		// We will then deliberately re-introduce safe tags via our Markdown rules.
		$text = esc_html($text);

		// ---- Fenced code blocks ---- (must run before inline code).
		$text = preg_replace_callback(
			'/```(?:[a-zA-Z0-9]*)\n?(.*?)```/s',
			static function (array $m): string {
				return '<pre><code>' . $m[1] . '</code></pre>';
			},
			$text
		);

		// ---- Inline code ----.
		$text = preg_replace('/`([^`\n]+)`/', '<code>$1</code>', (string) $text);

		// ---- ATX headings h3-h6 ----.
		$text = preg_replace('/^######\s(.+)$/m', '<h6>$1</h6>', (string) $text);
		$text = preg_replace('/^#####\s(.+)$/m', '<h5>$1</h5>', (string) $text);
		$text = preg_replace('/^####\s(.+)$/m', '<h4>$1</h4>', (string) $text);
		$text = preg_replace('/^###\s(.+)$/m', '<h3>$1</h3>', (string) $text);
		// h1/h2 intentionally omitted (too prominent for chat context).

		// ---- Bold (**text** or __text__) ----.
		$text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', (string) $text);
		$text = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', (string) $text);

		// ---- Italic (*text* or _text_) — single asterisk/underscore ----.
		$text = preg_replace('/\*([^\*\n]+)\*/s', '<em>$1</em>', (string) $text);
		$text = preg_replace('/_([^_\n]+)_/s', '<em>$1</em>', (string) $text);

		// ---- Strikethrough ----
		$text = preg_replace('/~~(.+?)~~/s', '<del>$1</del>', (string) $text);

		// ---- Horizontal rules (--- or ***) ----.
		$text = preg_replace('/^(\-{3,}|\*{3,})$/m', '<hr>', (string) $text);

		// ---- Links [text](url) ----.
		$text = preg_replace_callback(
			'/\[([^\]]+)\]\(([^)]+)\)/',
			static function (array $m): string {
				$label = $m[1];
				$href = $m[2];
				// Basic scheme check — full sanitization done later in sanitize_links().
				$scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
				$safe = in_array($scheme, ['https', 'http', 'mailto', 'tel'], true)
					|| strpos($href, '/') === 0
					|| strpos($href, '#') === 0;

				if (! $safe) {
					return esc_html($label);
				}

				return '<a href="' . esc_url($href) . '" target="_blank" rel="noopener noreferrer">' . esc_html($label) . '</a>';
			},
			(string) $text
		);

		// ---- Block-level elements: lists and blockquotes ----.
		$text = self::convert_lists((string) $text);
		$text = self::convert_blockquotes((string) $text);

		// ---- Paragraphs (blank-line separation) ----.
		$text = self::convert_paragraphs((string) $text);

		return (string) $text;
	}

	// =========================================================================
	// Link safety
	// =========================================================================

	/**
	 * Post-process all <a> tags in HTML to enforce safe href and attributes.
	 *
	 * Ensures:
	 *   - href scheme is in allowed list (or relative)
	 *   - All links open in a new tab with noopener noreferrer
	 *   - Malicious javascript: URIs are stripped
	 *
	 * @param  string $html kses-filtered HTML.
	 * @return string HTML with enforced link safety.
	 */
	public static function sanitize_links(string $html): string
	{
		return (string) preg_replace_callback(
			'/<a\s+([^>]*)>/i',
			static function (array $m): string {
				$attrs = $m[1];

				// Extract href.
				$href = '';
				if (preg_match('/href=["\']([^"\']*)["\']/', $attrs, $hm)) {
					$href = $hm[1];
				}

				// Validate scheme.
				if ('' !== $href) {
					$scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
					$relative = (strpos($href, '/') === 0 || strpos($href, '#') === 0);
					$allowed = in_array($scheme, ['https', 'http', 'mailto', 'tel'], true);

					if (! $allowed && ! $relative) {
						// Strip invalid href entirely.
						$href = '#';
					}
				}

				$safe_href = esc_url($href);

				return '<a href="' . $safe_href . '" target="_blank" rel="noopener noreferrer">';
			},
			$html
		);
	}

	// =========================================================================
	// Private Markdown helpers
	// =========================================================================

	/**
	 * Convert Markdown lists to HTML <ul>/<ol> structures.
	 *
	 * @param  string $text
	 * @return string
	 */
	private static function convert_lists(string $text): string
	{
		$lines = explode("\n", $text);
		$out = [];
		$in_ul = false;
		$in_ol = false;

		foreach ($lines as $line) {
			// Unordered list item.
			if (preg_match('/^[\-\*]\s+(.+)$/', $line, $m)) {
				if ($in_ol) {
					$out[] = '</ol>';
					$in_ol = false;
				}
				if (! $in_ul) {
					$out[] = '<ul>';
					$in_ul = true;
				}
				$out[] = '<li>' . $m[1] . '</li>';
				continue;
			}

			// Ordered list item.
			if (preg_match('/^\d+\.\s+(.+)$/', $line, $m)) {
				if ($in_ul) {
					$out[] = '</ul>';
					$in_ul = false;
				}
				if (! $in_ol) {
					$out[] = '<ol>';
					$in_ol = true;
				}
				$out[] = '<li>' . $m[1] . '</li>';
				continue;
			}

			// Close open lists.
			if ($in_ul) {
				$out[] = '</ul>';
				$in_ul = false;
			}
			if ($in_ol) {
				$out[] = '</ol>';
				$in_ol = false;
			}

			$out[] = $line;
		}

		if ($in_ul) {
			$out[] = '</ul>';
		}
		if ($in_ol) {
			$out[] = '</ol>';
		}

		return implode("\n", $out);
	}

	/**
	 * Convert Markdown blockquotes to HTML <blockquote> elements.
	 *
	 * @param  string $text
	 * @return string
	 */
	private static function convert_blockquotes(string $text): string
	{
		$lines = explode("\n", $text);
		$out = [];
		$in_bq = false;

		foreach ($lines as $line) {
			if (preg_match('/^>\s*(.*)$/', $line, $m)) {
				if (! $in_bq) {
					$out[] = '<blockquote>';
					$in_bq = true;
				}
				$out[] = $m[1];
				continue;
			}

			if ($in_bq) {
				$out[] = '</blockquote>';
				$in_bq = false;
			}

			$out[] = $line;
		}

		if ($in_bq) {
			$out[] = '</blockquote>';
		}

		return implode("\n", $out);
	}

	/**
	 * Wrap text separated by blank lines into <p> elements.
	 * Skips lines that are already block-level HTML elements.
	 *
	 * @param  string $text
	 * @return string
	 */
	private static function convert_paragraphs(string $text): string
	{
		$block_tags = 'h[3-6]|ul|ol|li|pre|blockquote|hr|table|thead|tbody|tr|th|td';

		// Split on one or more blank lines.
		$blocks = preg_split('/\n{2,}/', trim($text));
		if (! is_array($blocks)) {
			return $text;
		}

		$out = [];

		foreach ($blocks as $block) {
			$block = trim($block);
			if ('' === $block) {
				continue;
			}

			// Don't wrap block-level elements in <p>.
			if (preg_match('/^<(?:' . $block_tags . ')[\s>\/]/i', $block) ||
				preg_match('/<\/(?:' . $block_tags . ')>$/i', $block)) {
				// Convert remaining bare newlines to <br> within block.
				$out[] = $block;
				continue;
			}

			// Convert single newlines within paragraph to <br>.
			$para = str_replace("\n", '<br>', $block);
			$out[] = '<p>' . $para . '</p>';
		}

		return implode('', $out);
	}
}
