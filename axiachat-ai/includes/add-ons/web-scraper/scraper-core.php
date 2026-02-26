<?php
/**
 * Web Scraper – Core extraction engine
 *
 * Robust article extractor using DOMDocument + XPath.
 * Based on WEB_SCRAPER_GUIDE.md — designed to work across thousands of
 * websites (WordPress, news sites, custom CMS) without breaking.
 *
 * @package AxiaChat
 * @subpackage WebScraper
 * @since 3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Extract article content from a URL.
 *
 * @param string $url            The URL to scrape.
 * @param bool   $include_url    Whether to prepend the source URL to the content.
 * @return array|WP_Error {
 *     @type string $title           Article title.
 *     @type string $content         Full extracted text.
 *     @type string $excerpt         First ~40 words.
 *     @type string $url             The source URL.
 *     @type string $content_warning '' | 'short_content' | 'no_content'
 * }
 */
function aichat_webscraper_extract_content( $url, $include_url = false ) {

    $url = esc_url_raw( $url );
    if ( empty( $url ) ) {
        return new WP_Error( 'invalid_url', __( 'Invalid or empty URL.', 'axiachat-ai' ) );
    }

    aichat_webscraper_log( "Fetching: {$url}" );

    // ─── 1. HTTP Request (Anti-Block headers) ─────────────────────
    $parsed  = wp_parse_url( $url );
    $referer = ( $parsed['scheme'] ?? 'https' ) . '://' . ( $parsed['host'] ?? '' );

    $options = [
        'timeout'     => 30,
        'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'sslverify'   => false,
        'redirection' => 5,
        'headers'     => [
            'Accept'                      => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language'             => 'en-US,en;q=0.9,es;q=0.8',
            'Accept-Encoding'             => 'identity',
            'Cache-Control'               => 'no-cache',
            'Referer'                     => $referer,
            'Sec-Fetch-Dest'              => 'document',
            'Sec-Fetch-Mode'              => 'navigate',
            'Sec-Fetch-Site'              => 'same-origin',
            'Sec-Fetch-User'              => '?1',
            'Upgrade-Insecure-Requests'   => '1',
            'Sec-Ch-Ua'                   => '"Chromium";v="131", "Not_A Brand";v="24"',
            'Sec-Ch-Ua-Mobile'            => '?0',
            'Sec-Ch-Ua-Platform'          => '"Windows"',
        ],
    ];

    $response = wp_remote_get( $url, $options );

    if ( is_wp_error( $response ) ) {
        aichat_webscraper_log( 'HTTP error: ' . $response->get_error_message() );
        return new WP_Error( 'http_error', $response->get_error_message() );
    }

    $status_code = wp_remote_retrieve_response_code( $response );

    if ( 403 === $status_code ) {
        return new WP_Error(
            'access_denied',
            __( 'Access denied (403). The website is blocking automated requests from your server. Try pasting the content manually.', 'axiachat-ai' )
        );
    }

    if ( 200 !== $status_code ) {
        return new WP_Error( 'http_status', sprintf( __( 'HTTP error %d fetching the URL.', 'axiachat-ai' ), $status_code ) );
    }

    $html = wp_remote_retrieve_body( $response );
    if ( empty( $html ) ) {
        return new WP_Error( 'empty_body', __( 'The page returned an empty response.', 'axiachat-ai' ) );
    }

    // ─── 2. DOM Setup ─────────────────────────────────────────────
    libxml_use_internal_errors( true );

    $doc = new DOMDocument();
    $doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR );
    libxml_clear_errors();

    $xpath = new DOMXPath( $doc );

    // ─── 3. Title Extraction (3-tier fallback) ────────────────────
    $title = '';

    // 3a. og:title
    $og = $xpath->query( '//meta[@property="og:title"]/@content' );
    if ( $og && $og->length > 0 ) {
        $title = trim( $og->item( 0 )->nodeValue );
    }

    // 3b. <h1>
    if ( empty( $title ) ) {
        $h1 = $xpath->query( '//h1' );
        if ( $h1 && $h1->length > 0 ) {
            $title = trim( $h1->item( 0 )->textContent );
        }
    }

    // 3c. <title>
    if ( empty( $title ) ) {
        $t = $xpath->query( '//title' );
        if ( $t && $t->length > 0 ) {
            $title = trim( $t->item( 0 )->textContent );
        }
    }

    $title = sanitize_text_field( $title );

    // ─── 4. Noise Removal ─────────────────────────────────────────

    // Phase A: Unconditional tag removal
    $remove_tags = [ 'script', 'style', 'nav', 'header', 'footer', 'aside', 'iframe', 'noscript', 'form', 'svg', 'figcaption' ];
    foreach ( $remove_tags as $tag ) {
        $elements = $xpath->query( '//' . $tag );
        if ( ! $elements ) {
            continue;
        }
        $to_remove = [];
        foreach ( $elements as $element ) {
            $to_remove[] = $element;
        }
        foreach ( $to_remove as $element ) {
            if ( $element->parentNode ) {
                $element->parentNode->removeChild( $element );
            }
        }
    }

    // Phase B: Noise pattern removal WITH paragraph safety check
    $noise_patterns = [
        '//*[contains(@class, "comment")]',
        '//*[contains(@class, "sidebar")]',
        '//*[contains(@class, "widget")]',
        '//*[contains(@class, "share")]',
        '//*[contains(@class, "social")]',
        '//*[contains(@class, "related")]',
        '//*[contains(@class, "advertisement")]',
        '//*[contains(@class, "ad-")]',
        '//*[contains(@class, "cookie")]',
        '//*[contains(@class, "newsletter")]',
        '//*[contains(@class, "popup")]',
        '//*[contains(@class, "author-bio")]',
        '//*[contains(@class, "post-tags")]',
        '//*[contains(@class, "breadcrumb")]',
        '//*[contains(@class, "navigation")]',
        '//*[contains(@class, "paginat")]',
        '//*[contains(@id, "comment")]',
        '//*[contains(@id, "sidebar")]',
        '//*[contains(@id, "footer")]',
        '//*[contains(@id, "header")]',
        '//*[contains(@id, "newsletter")]',
    ];

    $to_remove = [];
    foreach ( $noise_patterns as $pattern ) {
        $elements = $xpath->query( $pattern );
        if ( ! $elements ) {
            continue;
        }
        foreach ( $elements as $element ) {
            // Safety check: count substantial paragraphs inside
            $p_count = 0;
            $paras   = $xpath->query( './/p', $element );
            if ( $paras ) {
                foreach ( $paras as $p ) {
                    if ( strlen( trim( $p->textContent ) ) > 40 ) {
                        $p_count++;
                    }
                }
            }
            // Only remove if < 3 real paragraphs (not a content container)
            if ( $p_count < 3 ) {
                $to_remove[] = $element;
            }
        }
    }
    foreach ( $to_remove as $element ) {
        if ( $element->parentNode ) {
            $element->parentNode->removeChild( $element );
        }
    }

    // ─── 5. Content Extraction (4-Strategy Pipeline) ──────────────
    $content = '';

    // Strategy 1: Schema.org articleBody
    $schema_nodes = $xpath->query( '//*[@itemprop="articleBody"]' );
    if ( $schema_nodes && $schema_nodes->length > 0 ) {
        $content = aichat_webscraper_get_text_content( $schema_nodes->item( 0 ) );
    }

    // Strategy 2: CSS class selectors (30+ patterns)
    if ( strlen( trim( $content ) ) < 200 ) {
        $content_selectors = [
            // Standard WordPress
            '//*[contains(@class, "entry-content")]',
            '//*[contains(@class, "post-content")]',
            '//*[contains(@class, "article-content")]',
            '//*[contains(@class, "article-body")]',
            '//*[contains(@class, "post-body")]',
            '//*[contains(@class, "single-content")]',

            // TagDiv themes
            '//*[contains(@class, "td-post-content")]',
            '//*[contains(@class, "tdb-block-inner")]',
            '//*[contains(@class, "tdb_single_content")]',
            '//*[contains(@class, "td-module-content")]',
            '//*[contains(@class, "td-ss-main-content")]',

            // Flavor variants
            '//*[contains(@class, "flavor-content")]',
            '//*[contains(@class, "the_content_wrapper")]',
            '//*[contains(@class, "single-post-content")]',
            '//*[contains(@class, "flavor-text")]',
            '//*[contains(@class, "flavor-article")]',

            // WPBakery Page Builder
            '//*[contains(@class, "wpb_text_column")]',

            // GeneratePress
            '//*[contains(@class, "inside-article")]',

            // Astra
            '//*[contains(@class, "ast-post-format-")]',

            // OceanWP
            '//*[contains(@class, "entry")]',

            // Generic / news / magazine
            '//*[contains(@class, "article__body")]',
            '//*[contains(@class, "story-body")]',
            '//*[contains(@class, "c-article-body")]',
            '//*[contains(@class, "content-area")]',
            '//*[@id="content"]',
            '//*[@role="main"]',
            '//main',
        ];

        foreach ( $content_selectors as $selector ) {
            $nodes = $xpath->query( $selector );
            if ( $nodes && $nodes->length > 0 ) {
                $candidate = aichat_webscraper_get_text_content( $nodes->item( 0 ) );
                if ( strlen( trim( $candidate ) ) > strlen( trim( $content ) ) ) {
                    $content = $candidate;
                }
            }
        }
    }

    // Strategy 3: <article> tag
    if ( strlen( trim( $content ) ) < 200 ) {
        $article_nodes = $xpath->query( '//article' );
        if ( $article_nodes && $article_nodes->length > 0 ) {
            $candidate = aichat_webscraper_get_text_content( $article_nodes->item( 0 ) );
            if ( strlen( trim( $candidate ) ) > strlen( trim( $content ) ) ) {
                $content = $candidate;
            }
        }
    }

    // Strategy 4: Highest paragraph-count <div> (Readability heuristic)
    if ( strlen( trim( $content ) ) < 200 ) {
        $divs       = $xpath->query( '//div' );
        $best_score = 0;
        $best_div   = null;

        if ( $divs ) {
            foreach ( $divs as $div ) {
                $paragraphs = $xpath->query( './/p', $div );
                $score      = 0;
                $text_len   = 0;

                if ( $paragraphs ) {
                    foreach ( $paragraphs as $p ) {
                        $p_text = trim( $p->textContent );
                        if ( strlen( $p_text ) > 25 ) {
                            $score++;
                            $text_len += strlen( $p_text );
                        }
                    }
                }

                // Penalize deeply nested containers
                $child_divs      = $xpath->query( './div', $div );
                $nesting_penalty = $child_divs ? $child_divs->length * 10 : 0;

                $total_score = $score * 100 + $text_len - $nesting_penalty;

                if ( $total_score > $best_score && $score >= 2 ) {
                    $best_score = $total_score;
                    $best_div   = $div;
                }
            }
        }

        if ( $best_div ) {
            $candidate = aichat_webscraper_get_text_content( $best_div );
            if ( strlen( trim( $candidate ) ) > strlen( trim( $content ) ) ) {
                $content = $candidate;
            }
        }
    }

    // ─── 6. Post-processing ───────────────────────────────────────

    // Clean up whitespace
    $content = preg_replace( '/[ \t]+/', ' ', $content );
    $content = preg_replace( '/\n{3,}/', "\n\n", $content );
    $content = trim( $content );

    // Prepend source URL if requested
    if ( $include_url && ! empty( $content ) ) {
        $content = sprintf( __( 'Source: %s', 'axiachat-ai' ), $url ) . "\n\n" . $content;
    }

    // Truncate (15 000 chars ≈ 2 500–3 000 words)
    if ( strlen( $content ) > 15000 ) {
        $content = substr( $content, 0, 15000 ) . "\n\n[" . __( 'Content truncated...', 'axiachat-ai' ) . ']';
    }

    // Determine warning level
    $content_warning = '';
    if ( empty( $content ) && ! empty( $title ) ) {
        $content_warning = 'no_content';
    } elseif ( strlen( $content ) > 0 && strlen( $content ) < 200 ) {
        $content_warning = 'short_content';
    }

    if ( empty( $content ) && empty( $title ) ) {
        return new WP_Error( 'extraction_failed', __( 'Could not extract any content from this URL.', 'axiachat-ai' ) );
    }

    // Generate excerpt
    $excerpt = '';
    if ( ! empty( $content ) ) {
        $excerpt = wp_trim_words( $content, 40, '…' );
    }

    aichat_webscraper_log( "Extracted: title=" . strlen( $title ) . " chars, content=" . strlen( $content ) . " chars, warning={$content_warning}" );

    return [
        'title'           => $title,
        'content'         => $content,
        'excerpt'         => $excerpt,
        'url'             => $url,
        'content_warning' => $content_warning,
    ];
}

/**
 * Recursive DOM text extractor — preserves paragraph structure.
 *
 * @param DOMNode $node The DOM node to extract text from.
 * @return string Clean text with paragraph breaks.
 */
function aichat_webscraper_get_text_content( $node ) {
    $text = '';

    if ( ! $node->childNodes ) {
        return $text;
    }

    foreach ( $node->childNodes as $child ) {
        if ( XML_TEXT_NODE === $child->nodeType ) {
            $text .= trim( $child->textContent ) . ' ';
        } elseif ( XML_ELEMENT_NODE === $child->nodeType ) {
            $tag = strtolower( $child->nodeName );
            // Block elements get double newlines
            if ( in_array( $tag, [ 'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'br', 'blockquote', 'pre', 'tr' ], true ) ) {
                $inner = aichat_webscraper_get_text_content( $child );
                if ( ! empty( trim( $inner ) ) ) {
                    $text .= "\n\n" . trim( $inner );
                }
            } else {
                // Inline elements (span, a, strong, em, etc.)
                $text .= aichat_webscraper_get_text_content( $child );
            }
        }
    }

    return $text;
}

/**
 * Debug logger.
 *
 * @param string $message Log message.
 */
function aichat_webscraper_log( $message ) {
    if ( function_exists( 'aichat_log_debug' ) ) {
        aichat_log_debug( '[AIChat WebScraper] ' . $message );
    }
}
