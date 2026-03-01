<?php

/**
 * Web Content Source Adapter — indexes web pages as a RAG knowledge source.
 *
 * Crawls one or more configured root URLs to a configurable depth (1–3 levels),
 * extracts clean text from each page (stripping nav/header/footer/scripts),
 * respects robots.txt, and rate-limits requests to avoid hammering remote servers.
 *
 * Configured URL entries are stored in the `wpaic_web_sources` WP option as a
 * JSON-encoded array: [{"id":1,"url":"...","label":"...","depth":1}, ...]
 *
 * The `item_id` passed to get_content() corresponds to the `id` field in that
 * option, NOT a WordPress post ID.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Rag\Adapters;

use WPAIC\Rag\Content_Source;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Web_Source
 *
 * @since 1.0.0
 * @package WPAIC\Rag\Adapters
 */
class Web_Source implements Content_Source
{
	/** Source machine ID. */
	private const SOURCE_ID = 'web';

	/** WP option key where configured URL entries are stored. */
	public const OPTION_KEY = 'wpaic_web_sources';

	/** HTTP request timeout in seconds. */
	private const REQUEST_TIMEOUT = 15;

	/** User-Agent header sent with all requests. */
	private const USER_AGENT = 'WPAICBot/1.0 (WordPress AI Chatbot; contact your-site-admin)';

	/** Microseconds to sleep between crawl requests to avoid rate-limit. */
	private const CRAWL_DELAY_US = 500000; // 0.5 s

	/** Maximum allowed crawl depth (hard cap). */
	private const MAX_DEPTH = 3;

	/** {@inheritdoc} */
	public function get_id(): string
	{
		return self::SOURCE_ID;
	}

	/** {@inheritdoc} */
	public function get_label(): string
	{
		return __('Web Pages', 'wp-ai-chatbot');
	}

	/** {@inheritdoc} */
	public function get_items(array $options = []): array
	{
		$sources = self::get_stored_sources();
		$items = [];

		foreach ($sources as $source) {
			$items[] = [
				'id' => (int) $source['id'],
				'title' => (string) $source['label'],
				'type' => 'web',
			];
		}

		return $items;
	}

	/** {@inheritdoc} */
	public function get_content(int $item_id)
	{
		$source = $this->find_source($item_id);

		if (null === $source) {
			return false;
		}

		$url = (string) $source['url'];
		$depth = min((int) $source['depth'], self::MAX_DEPTH);
		$label = (string) $source['label'];

		$visited = [];
		$pages = $this->crawl($url, $depth, $visited);

		if (empty($pages)) {
			return false;
		}

		// Assemble content: each crawled page becomes a clearly labelled section.
		$parts = [];

		foreach ($pages as $page_url => $page_data) {
			$parts[] = sprintf(
				"=== %s ===\nSource: %s\n\n%s",
				$page_data['title'],
				$page_url,
				$page_data['text']
			);
		}

		$content = implode("\n\n---\n\n", $parts);

		return [
			'title' => $label,
			'content' => $content,
			'url' => $url,
			'type' => 'web',
			'meta' => [
				'source_url' => $url,
				'pages_crawled' => count($pages),
				'depth' => $depth,
			],
		];
	}

	// =========================================================================
	// Static source-management helpers (used by admin REST handlers)
	// =========================================================================

	/**
	 * Return all configured web source entries.
	 *
	 * @return array<int, array{id: int, url: string, label: string, depth: int}>
	 */
	public static function get_stored_sources(): array
	{
		$raw = (string) get_option(self::OPTION_KEY, '[]');
		$decoded = json_decode($raw, true);

		return is_array($decoded) ? $decoded : [];
	}

	/**
	 * Create or update a web source entry. Returns the (new) entry ID.
	 *
	 * @param  array{id?: int, url: string, label: string, depth?: int} $data Entry data.
	 * @return int Assigned entry ID.
	 */
	public static function upsert_source(array $data): int
	{
		$sources = self::get_stored_sources();
		$id = (int) ($data['id'] ?? 0);
		$url = esc_url_raw((string) ($data['url'] ?? ''));
		$label = sanitize_text_field((string) ($data['label'] ?? ''));
		$depth = (int) min(max(1, (int) ($data['depth'] ?? 1)), self::MAX_DEPTH);

		if ($id > 0) {
			// Update existing entry.
			foreach ($sources as &$src) {
				if ((int) $src['id'] === $id) {
					$src['url'] = $url;
					$src['label'] = $label;
					$src['depth'] = $depth;
					break;
				}
			}
			unset($src);
		} else {
			// Create new entry: assign next available integer ID.
			$max_id = 0;

			foreach ($sources as $src) {
				$max_id = max($max_id, (int) $src['id']);
			}

			$id = $max_id + 1;

			$sources[] = [
				'id' => $id,
				'url' => $url,
				'label' => $label,
				'depth' => $depth,
			];
		}

		update_option(self::OPTION_KEY, wp_json_encode($sources), false);

		return $id;
	}

	/**
	 * Delete a web source entry by ID.
	 *
	 * @param  int $id Entry ID to delete.
	 * @return bool True if the option was updated.
	 */
	public static function delete_source(int $id): bool
	{
		$sources = self::get_stored_sources();

		$filtered = array_values(
			array_filter(
				$sources,
				static function (array $src) use ($id): bool {
					return (int) $src['id'] !== $id;
				}
			)
		);

		return (bool) update_option(self::OPTION_KEY, wp_json_encode($filtered), false);
	}

	// =========================================================================
	// Internal — crawling
	// =========================================================================

	/**
	 * Find a stored source entry by its integer ID.
	 *
	 * @param  int $item_id Entry ID.
	 * @return array{id: int, url: string, label: string, depth: int}|null
	 */
	private function find_source(int $item_id): ?array
	{
		foreach (self::get_stored_sources() as $source) {
			if ((int) $source['id'] === $item_id) {
				return $source;
			}
		}

		return null;
	}

	/**
	 * BFS crawl of $start_url up to $depth levels, respecting robots.txt.
	 *
	 * Stays on the same hostname. Applies a 0.5 s inter-request delay after
	 * the first page to avoid overloading remote servers.
	 *
	 * @param  string   $start_url Root URL to crawl.
	 * @param  int      $depth     Maximum crawl depth (1 = root page only).
	 * @param  string[] $visited   Already-visited URLs, passed by reference.
	 * @return array<string, array{title: string, text: string}>  Pages keyed by URL.
	 */
	private function crawl(string $start_url, int $depth, array &$visited): array
	{
		// Validate and check robots.txt for the root URL.
		if (! $this->robots_allows($start_url)) {
			return [];
		}

		$start_host = (string) wp_parse_url($start_url, PHP_URL_HOST);
		$pages = [];
		$queue = [['url' => $start_url, 'remaining' => $depth]];

		while (! empty($queue)) {
			$item = array_shift($queue);
			$url = (string) $item['url'];
			$remaining = (int) $item['remaining'];

			if (isset($visited[$url]) || $remaining < 1) {
				continue;
			}

			$visited[$url] = true;

			// Rate-limit: pause between requests (skip on first fetch).
			if (count($visited) > 1) {
				usleep(self::CRAWL_DELAY_US);
			}

			$page = $this->fetch_page($url);

			if (null === $page) {
				continue;
			}

			$pages[$url] = [
				'title' => $page['title'],
				'text' => $page['text'],
			];

			// Enqueue same-host links for the next depth level.
			if ($remaining > 1) {
				foreach ($page['links'] as $link) {
					$link_host = (string) wp_parse_url($link, PHP_URL_HOST);

					if ($link_host === $start_host && ! isset($visited[$link])) {
						$queue[] = ['url' => $link, 'remaining' => $remaining - 1];
					}
				}
			}
		}

		return $pages;
	}

	/**
	 * Fetch a single URL and return title, clean text, and discovered links.
	 *
	 * @param  string $url URL to fetch.
	 * @return array{title: string, text: string, links: string[]}|null Null on any fetch failure.
	 */
	private function fetch_page(string $url): ?array
	{
		$response = wp_remote_get(
			$url,
			[
				'timeout' => self::REQUEST_TIMEOUT,
				'user-agent' => self::USER_AGENT,
				'sslverify' => true,
			]
		);

		if (is_wp_error($response)) {
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code($response);

		if ($code < 200 || $code >= 400) {
			return null;
		}

		$content_type = (string) wp_remote_retrieve_header($response, 'content-type');

		if (false === strpos($content_type, 'text/html')) {
			return null;
		}

		$html = (string) wp_remote_retrieve_body($response);

		if ('' === $html) {
			return null;
		}

		return $this->parse_html($html, $url);
	}

	/**
	 * Parse an HTML document into title, body text, and absolute links.
	 *
	 * Removes noise elements (nav, header, footer, script, style, aside,
	 * form, noscript, iframe, svg) and elements with common navigation
	 * roles/classes before extracting text content.
	 *
	 * @param  string $html     Raw HTML content.
	 * @param  string $base_url Base URL used to resolve relative hrefs.
	 * @return array{title: string, text: string, links: string[]}
	 */
	private function parse_html(string $html, string $base_url): array
	{
		$dom = new \DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
		libxml_clear_errors();

		$xpath = new \DOMXPath($dom);

		// Extract <title>.
		$title_nodes = $xpath->query('//title');
		$title = '';

		if ($title_nodes && $title_nodes->length > 0) {
			$title = trim((string) $title_nodes->item(0)->textContent);
		}

		// Remove structural noise tags.
		foreach (['nav', 'header', 'footer', 'script', 'style', 'aside', 'form', 'noscript', 'iframe', 'svg'] as $tag) {
			$nodes = $dom->getElementsByTagName($tag);

			while ($nodes->length > 0) {
				$node = $nodes->item(0);

				if (null !== $node && null !== $node->parentNode) {
					$node->parentNode->removeChild($node);
				}
			}
		}

		// Remove common navigation/sidebar elements by ARIA role or CSS class.
		$noise_selectors = [
			'//*[@role="navigation"]',
			'//*[@role="banner"]',
			'//*[@role="contentinfo"]',
			'//*[contains(concat(" ",normalize-space(@class)," ")," navigation ")]',
			'//*[contains(concat(" ",normalize-space(@class)," ")," sidebar ")]',
			'//*[contains(concat(" ",normalize-space(@class)," ")," widget ")]',
			'//*[contains(concat(" ",normalize-space(@class)," ")," cookie-banner ")]',
			'//*[contains(concat(" ",normalize-space(@class)," ")," popup ")]',
		];

		foreach ($noise_selectors as $selector) {
			$nodes = $xpath->query($selector);

			if (! $nodes) {
				continue;
			}

			$to_remove = [];

			foreach ($nodes as $node) {
				$to_remove[] = $node;
			}

			foreach ($to_remove as $node) {
				if (null !== $node->parentNode) {
					$node->parentNode->removeChild($node);
				}
			}
		}

		// Extract body text.
		$body_nodes = $dom->getElementsByTagName('body');
		$text = '';

		if ($body_nodes->length > 0) {
			$text = (string) $body_nodes->item(0)->textContent;
		}

		$text = (string) preg_replace('/[ \t]{2,}/', ' ', $text);
		$text = (string) preg_replace('/\n{3,}/', "\n\n", $text);
		$text = trim($text);

		// Extract and resolve all links for depth crawling.
		$links = [];
		$link_nodes = $xpath->query('//a[@href]');

		if ($link_nodes) {
			foreach ($link_nodes as $link_node) {
				$href = (string) $link_node->getAttribute('href');
				$absolute = $this->resolve_url($href, $base_url);

				if ('' !== $absolute) {
					$links[] = $absolute;
				}
			}
		}

		return [
			'title' => ('' !== $title) ? $title : $base_url,
			'text' => $text,
			'links' => array_values(array_unique($links)),
		];
	}

	/**
	 * Resolve a potentially-relative URL against a base URL.
	 *
	 * Returns empty string for anchors, mailto:, tel:, javascript:, data: URIs.
	 *
	 * @param  string $href     Raw href attribute value.
	 * @param  string $base_url Absolute base URL of the current page.
	 * @return string Absolute URL, or empty string if non-HTTP.
	 */
	private function resolve_url(string $href, string $base_url): string
	{
		$href = trim($href);

		if ('' === $href) {
			return '';
		}

		// Skip non-navigable schemes.
		foreach (['#', 'mailto:', 'tel:', 'javascript:', 'data:'] as $skip) {
			if (0 === strpos($href, $skip)) {
				return '';
			}
		}

		// Strip fragment.
		$hash_pos = strpos($href, '#');

		if (false !== $hash_pos) {
			$href = substr($href, 0, $hash_pos);
		}

		if ('' === $href) {
			return '';
		}

		// Already absolute HTTP/HTTPS.
		if (0 === strpos($href, 'http://') || 0 === strpos($href, 'https://')) {
			return $href;
		}

		$parsed = wp_parse_url($base_url);
		$scheme = (string) ($parsed['scheme'] ?? 'https');
		$host = (string) ($parsed['host'] ?? '');
		$base_path = (string) ($parsed['path'] ?? '/');

		if ('' === $host) {
			return '';
		}

		// Protocol-relative URL.
		if (0 === strpos($href, '//')) {
			return $scheme . ':' . $href;
		}

		// Root-relative URL.
		if (0 === strpos($href, '/')) {
			return $scheme . '://' . $host . $href;
		}

		// Relative path — resolve against the directory of the base path.
		$base_dir = rtrim(dirname($base_path), '/');

		return $scheme . '://' . $host . $base_dir . '/' . $href;
	}

	// =========================================================================
	// Internal — robots.txt
	// =========================================================================

	/**
	 * Fetch and parse robots.txt to determine if the given URL is crawlable.
	 *
	 * Uses a conservative "allowed unless explicitly disallowed" approach.
	 * Returns true (allowed) when robots.txt is absent (404) or unreachable.
	 *
	 * @param  string $url URL to check.
	 * @return bool True if crawling is allowed.
	 */
	private function robots_allows(string $url): bool
	{
		$parsed = wp_parse_url($url);
		$scheme = (string) ($parsed['scheme'] ?? 'https');
		$host = (string) ($parsed['host'] ?? '');
		$path = (string) ($parsed['path'] ?? '/');

		if ('' === $host) {
			return false;
		}

		$robots_url = $scheme . '://' . $host . '/robots.txt';

		$response = wp_remote_get(
			$robots_url,
			[
				'timeout' => 5,
				'user-agent' => self::USER_AGENT,
			]
		);

		if (is_wp_error($response)) {
			return true; // Unreachable robots.txt → allow.
		}

		$code = (int) wp_remote_retrieve_response_code($response);

		if (404 === $code) {
			return true; // No robots.txt → allow everything.
		}

		$body = (string) wp_remote_retrieve_body($response);

		if ('' === $body) {
			return true;
		}

		return $this->parse_robots_txt($body, $path);
	}

	/**
	 * Parse robots.txt and check if $path is crawlable by our user-agent.
	 *
	 * Implements the subset of the Robots Exclusion Protocol required for
	 * User-agent and Disallow directives. Wildcard (*) user-agent applies
	 * as a fallback.
	 *
	 * @param  string $body robots.txt body text.
	 * @param  string $path URL path to check (e.g. '/blog/post-1').
	 * @return bool True if $path is allowed.
	 */
	private function parse_robots_txt(string $body, string $path): bool
	{
		$lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $body));
		$applies = false;
		$disallowed = [];

		foreach ($lines as $line) {
			$line = trim($line);

			if ('' === $line || 0 === strpos($line, '#')) {
				continue;
			}

			if (0 === strncasecmp($line, 'User-agent:', 11)) {
				$ua = strtolower(trim(substr($line, 11)));
				// Match our bot name or wildcard.
				$applies = ('*' === $ua || false !== strpos($ua, 'wpaic') || false !== strpos($ua, 'wpai'));
				continue;
			}

			if ($applies && 0 === strncasecmp($line, 'Disallow:', 9)) {
				$disallow_path = trim(substr($line, 9));

				if ('' !== $disallow_path) {
					$disallowed[] = $disallow_path;
				}
			}
		}

		foreach ($disallowed as $disallow_path) {
			if (0 === strpos($path, $disallow_path)) {
				return false;
			}
		}

		return true;
	}
}
