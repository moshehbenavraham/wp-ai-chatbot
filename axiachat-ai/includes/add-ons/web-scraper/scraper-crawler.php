<?php
/**
 * Web Scraper – Crawler / Spider
 *
 * BFS crawler that discovers URLs from a root page.
 * Stays within the same domain, respects depth and page limits.
 *
 * @package AxiaChat
 * @subpackage WebScraper
 * @since 3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Crawl a website starting from a root URL.
 *
 * Uses BFS (breadth-first search) to discover pages within the same domain.
 *
 * @param string $root_url   The starting URL.
 * @param int    $max_depth  Maximum link depth to follow (1 = only links on root page).
 * @param int    $max_pages  Maximum number of pages to discover.
 * @return array {
 *     @type array  $urls    Discovered URLs (array of strings).
 *     @type int    $total   Total URLs found.
 *     @type string $domain  The crawled domain.
 *     @type array  $errors  Any errors encountered (array of strings).
 * }
 */
function aichat_webscraper_crawl( $root_url, $max_depth = 2, $max_pages = 50 ) {
    $root_url = esc_url_raw( $root_url );
    if ( empty( $root_url ) ) {
        return [
            'urls'   => [],
            'total'  => 0,
            'domain' => '',
            'errors' => [ __( 'Invalid root URL.', 'axiachat-ai' ) ],
        ];
    }

    // Enforce sane limits
    $max_depth = max( 1, min( 5, (int) $max_depth ) );
    $max_pages = max( 1, min( 200, (int) $max_pages ) );

    $parsed_root = wp_parse_url( $root_url );
    $root_host   = strtolower( $parsed_root['host'] ?? '' );
    $root_scheme = $parsed_root['scheme'] ?? 'https';

    if ( empty( $root_host ) ) {
        return [
            'urls'   => [],
            'total'  => 0,
            'domain' => '',
            'errors' => [ __( 'Could not determine domain from URL.', 'axiachat-ai' ) ],
        ];
    }

    aichat_webscraper_log( "Crawl start: {$root_url} (depth={$max_depth}, max={$max_pages})" );

    $discovered = [];   // url => depth
    $queue      = [];   // [ url, depth ]
    $errors     = [];

    // Normalize root URL
    $root_url_normalized = aichat_webscraper_normalize_url( $root_url );
    $discovered[ $root_url_normalized ] = 0;
    $queue[] = [ $root_url_normalized, 0 ];

    // HTTP options (lightweight — we only need links, not full content)
    $http_options = [
        'timeout'     => 20,
        'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'sslverify'   => false,
        'redirection' => 3,
        'headers'     => [
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9,es;q=0.8',
            'Accept-Encoding' => 'identity',
            'Referer'         => $root_scheme . '://' . $root_host,
        ],
    ];

    // File extensions to skip
    $skip_extensions = [
        'pdf', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico',
        'mp3', 'mp4', 'avi', 'mov', 'wmv', 'flv',
        'zip', 'rar', 'gz', 'tar', '7z',
        'css', 'js', 'json', 'xml', 'rss', 'atom',
        'woff', 'woff2', 'ttf', 'eot',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    ];

    $processed = 0;

    while ( ! empty( $queue ) && count( $discovered ) <= $max_pages ) {
        $item  = array_shift( $queue );
        $url   = $item[0];
        $depth = $item[1];

        // Don't crawl links beyond max_depth
        if ( $depth >= $max_depth ) {
            continue;
        }

        $processed++;

        // Safety: abort if we've processed too many pages (prevent runaway)
        if ( $processed > $max_pages * 3 ) {
            $errors[] = __( 'Crawl safety limit reached.', 'axiachat-ai' );
            break;
        }

        // Fetch the page
        $response = wp_remote_get( $url, $http_options );

        if ( is_wp_error( $response ) ) {
            $errors[] = sprintf( '%s: %s', $url, $response->get_error_message() );
            continue;
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status ) {
            $errors[] = sprintf( '%s: HTTP %d', $url, $status );
            continue;
        }

        // Only process HTML responses
        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        if ( $content_type && false === stripos( $content_type, 'text/html' ) ) {
            continue;
        }

        $html = wp_remote_retrieve_body( $response );
        if ( empty( $html ) ) {
            continue;
        }

        // Extract links from HTML
        $new_urls = aichat_webscraper_extract_links( $html, $url, $root_host, $root_scheme );

        foreach ( $new_urls as $new_url ) {
            if ( count( $discovered ) >= $max_pages ) {
                break;
            }

            // Skip known extensions
            $path_lower = strtolower( wp_parse_url( $new_url, PHP_URL_PATH ) ?? '' );
            $ext        = pathinfo( $path_lower, PATHINFO_EXTENSION );
            if ( in_array( $ext, $skip_extensions, true ) ) {
                continue;
            }

            // Skip common non-content paths
            if ( aichat_webscraper_is_skip_path( $path_lower ) ) {
                continue;
            }

            $normalized = aichat_webscraper_normalize_url( $new_url );
            if ( ! isset( $discovered[ $normalized ] ) ) {
                $discovered[ $normalized ] = $depth + 1;
                $queue[] = [ $normalized, $depth + 1 ];
            }
        }
    }

    $urls = array_keys( $discovered );

    aichat_webscraper_log( 'Crawl done: ' . count( $urls ) . ' URLs discovered, ' . count( $errors ) . ' errors' );

    return [
        'urls'   => $urls,
        'total'  => count( $urls ),
        'domain' => $root_host,
        'errors' => $errors,
    ];
}

/**
 * Extract all same-domain links from an HTML page.
 *
 * @param string $html        Raw HTML content.
 * @param string $page_url    The URL of the page (for resolving relative links).
 * @param string $root_host   The root domain to stay within.
 * @param string $root_scheme The root scheme (http/https).
 * @return array Array of absolute URLs.
 */
function aichat_webscraper_extract_links( $html, $page_url, $root_host, $root_scheme ) {
    $links = [];

    libxml_use_internal_errors( true );
    $doc = new DOMDocument();
    $doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR );
    libxml_clear_errors();

    $anchors = $doc->getElementsByTagName( 'a' );
    $parsed_page = wp_parse_url( $page_url );
    $page_base   = ( $parsed_page['scheme'] ?? $root_scheme ) . '://' . ( $parsed_page['host'] ?? $root_host );
    $page_path   = $parsed_page['path'] ?? '/';

    foreach ( $anchors as $a ) {
        $href = $a->getAttribute( 'href' );
        if ( empty( $href ) ) {
            continue;
        }

        // Skip anchors, javascript, mailto, tel
        if ( preg_match( '/^(#|javascript:|mailto:|tel:|data:)/i', $href ) ) {
            continue;
        }

        // Resolve relative URLs
        if ( 0 === strpos( $href, '//' ) ) {
            // Protocol-relative
            $href = $root_scheme . ':' . $href;
        } elseif ( 0 === strpos( $href, '/' ) ) {
            // Root-relative
            $href = $page_base . $href;
        } elseif ( ! preg_match( '#^https?://#i', $href ) ) {
            // Relative to current path
            $dir  = rtrim( dirname( $page_path ), '/' );
            $href = $page_base . $dir . '/' . $href;
        }

        // Parse and validate
        $parsed_href = wp_parse_url( $href );
        $href_host   = strtolower( $parsed_href['host'] ?? '' );

        // Stay within the same domain (allow www and non-www variants)
        $root_base = preg_replace( '/^www\./', '', $root_host );
        $href_base = preg_replace( '/^www\./', '', $href_host );

        if ( $href_base !== $root_base ) {
            continue;
        }

        // Strip fragment
        $href = preg_replace( '/#.*$/', '', $href );

        if ( ! empty( $href ) ) {
            $links[] = $href;
        }
    }

    return array_unique( $links );
}

/**
 * Normalize a URL for deduplication.
 *
 * @param string $url The URL to normalize.
 * @return string Normalized URL.
 */
function aichat_webscraper_normalize_url( $url ) {
    // Remove trailing slash for consistency (except root)
    $parsed = wp_parse_url( $url );
    $path   = $parsed['path'] ?? '/';

    // Don't remove trailing slash from root
    if ( '/' !== $path ) {
        $path = rtrim( $path, '/' );
    }

    $normalized = ( $parsed['scheme'] ?? 'https' ) . '://' . ( $parsed['host'] ?? '' ) . $path;

    // Include query string but sort params for consistency
    if ( ! empty( $parsed['query'] ) ) {
        parse_str( $parsed['query'], $params );
        ksort( $params );
        // Remove common tracking params
        unset( $params['utm_source'], $params['utm_medium'], $params['utm_campaign'], $params['utm_term'], $params['utm_content'] );
        unset( $params['fbclid'], $params['gclid'], $params['ref'] );
        if ( ! empty( $params ) ) {
            $normalized .= '?' . http_build_query( $params );
        }
    }

    return strtolower( $normalized );
}

/**
 * Check if a URL path should be skipped (non-content pages).
 *
 * @param string $path URL path (lowercase).
 * @return bool True to skip.
 */
function aichat_webscraper_is_skip_path( $path ) {
    $skip_patterns = [
        '/wp-admin',
        '/wp-login',
        '/wp-json',
        '/feed',
        '/xmlrpc',
        '/wp-content/uploads',
        '/wp-includes',
        '/cart',
        '/checkout',
        '/my-account',
        '/login',
        '/register',
        '/wp-cron',
        '/trackback',
        '/embed',
        '/amp/',
        '/print/',
    ];

    foreach ( $skip_patterns as $pattern ) {
        if ( false !== strpos( $path, $pattern ) ) {
            return true;
        }
    }

    return false;
}
