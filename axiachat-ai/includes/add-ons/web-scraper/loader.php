<?php
/**
 * Web Scraper Add-on Loader
 *
 * Enables importing external web pages as context sources for the chatbot.
 * Pages are scraped, converted to text, then stored as aichat_upload + aichat_upload_chunk
 * posts — identical to the PDF/TXT pipeline — so they flow through the same
 * embedding / indexing / RAG system without any changes elsewhere.
 *
 * @package AxiaChat
 * @subpackage WebScraper
 * @since 3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add-on metadata
if ( ! function_exists( 'aichat_web_scraper_addon_info' ) ) {
    function aichat_web_scraper_addon_info() {
        return [
            'id'          => 'web-scraper',
            'name'        => __( 'Web Pages Import', 'axiachat-ai' ),
            'description' => __( 'Import content from external websites to use as chatbot knowledge.', 'axiachat-ai' ),
            'version'     => '1.0.0',
            'author'      => 'AxiaChat AI',
            'requires'    => '3.0.0',
            'default'     => true,
        ];
    }
}

// Define constants
if ( ! defined( 'AICHAT_WEBSCRAPER_VERSION' ) ) {
    define( 'AICHAT_WEBSCRAPER_VERSION', '1.0.0' );
}
if ( ! defined( 'AICHAT_WEBSCRAPER_DIR' ) ) {
    define( 'AICHAT_WEBSCRAPER_DIR', __DIR__ . '/' );
}

// Load core components
require_once AICHAT_WEBSCRAPER_DIR . 'scraper-core.php';
require_once AICHAT_WEBSCRAPER_DIR . 'scraper-crawler.php';
require_once AICHAT_WEBSCRAPER_DIR . 'admin-ajax.php';
