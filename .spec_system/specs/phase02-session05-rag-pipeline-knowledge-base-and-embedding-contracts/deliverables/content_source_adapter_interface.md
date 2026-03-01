# Content Source Adapter Interface

**Session**: `phase02-session05-rag-pipeline-knowledge-base-and-embedding-contracts`
**Version**: 1.0
**Created**: 2026-03-01
**Resolves**: CFI-RAG-001 (partial -- source registration and extraction contract)

---

## 1. Overview

This document defines the `Source_Adapter_Interface` that every content source adapter must implement.
Source adapters are responsible for discovering content items and extracting clean text content for
downstream chunking and embedding.

Adapters register via the `wpaic_register_rag_sources` action hook. The `Ingestion_Pipeline` class
maintains an adapter registry and dispatches to the correct adapter by source ID when processing
queue items.

### Reference Anchors

| Source Plugin | File | Relevance |
|---------------|------|-----------|
| AxiaChat | `axiachat-ai/includes/contexto-create.php` | Primary: Per-type extraction (posts, pages, WC products, Elementor walker, shortcode expansion) |
| AxiaChat | `axiachat-ai/includes/contexto-functions.php` | Content type dispatch, indexing options pattern |
| MxChat | `mxchat-basic/admin/class-knowledge-manager.php` | Source breadth (8+ types), queue dispatch by item_type |
| Baachal | `baachal/includes/content-indexer.php` | N/A (no chunking/embedding; keyword baseline only) |

---

## 2. Source_Adapter_Interface

All adapters reside in the `WPAIC\RAG\Adapters` namespace under `includes/rag/adapters/`.

### PHP Signature

```php
<?php
namespace WPAIC\RAG;

/**
 * Source_Adapter_Interface
 *
 * Contract for RAG content source adapters. Every adapter registered with the
 * Ingestion_Pipeline must implement this interface.
 *
 * Adapters are responsible for discovering what content items exist in a given
 * source and extracting clean, plain-text content from those items for chunking
 * and embedding.
 *
 * @since 1.0.0
 * @package WPAIC\RAG
 */
interface Source_Adapter_Interface {

    /**
     * Return the unique canonical source identifier.
     *
     * Used as `item_type` in `wp_wpaic_queue`. Must be stable -- stored in DB.
     *
     * Canonical IDs: 'wp_post', 'wp_page', 'wc_product', 'manual_text', 'url',
     *                'sitemap', 'pdf'
     *
     * @return string Source ID (lowercase, underscores allowed, no spaces).
     */
    public function get_source_id();

    /**
     * Return a human-readable label for this source type.
     *
     * Used in admin UI source selection dropdowns.
     *
     * @return string Translated display label.
     */
    public function get_label();

    /**
     * Return the item types this adapter can process.
     *
     * An adapter may handle multiple item types. For example, a WordPress
     * content adapter may handle 'wp_post' and 'wp_page' as separate types.
     *
     * @return string[] Array of item type strings.
     */
    public function get_supported_item_types();

    /**
     * Discover all content items available from this source.
     *
     * Returns an array of item descriptors. Each descriptor contains enough
     * information to enqueue the item and later call extract_content().
     *
     * This method is called during "Add Source" or "Reindex All" operations.
     * It MUST be performant: use DB queries with LIMIT/OFFSET, never load
     * all post content into memory.
     *
     * @param int   $knowledge_id The knowledge context ID for this indexing run.
     * @param array $options {
     *     Adapter-specific discovery options.
     *
     *     @type array  $post_types       WP post types to include (wp_post/wp_page adapters).
     *     @type bool   $include_private  Include private/draft content (default false).
     *     @type int    $limit            Max items to discover (default 0 = all).
     *     @type int    $offset           Pagination offset (default 0).
     * }
     * @return array[] Array of item descriptors:
     *     @type string $item_type   The item type constant (e.g., 'wp_post').
     *     @type string $item_id     Unique item identifier (e.g., post ID as string).
     *     @type array  $item_data   Serializable data for later extraction.
     *         @type int    $post_id     WP post ID (for wp_post/wp_page/wc_product adapters).
     *         @type string $url         Source URL (for url/sitemap adapters).
     *         @type string $title       Item title (for display in queue).
     */
    public function discover_items( $knowledge_id, $options = array() );

    /**
     * Extract clean text content from a single item.
     *
     * Returns plain text suitable for chunking. The adapter is responsible for:
     * - Stripping HTML/shortcodes/page-builder markup
     * - Expanding page builder content (Elementor, WPBakery) when available
     * - Normalizing whitespace (single spaces, no excessive line breaks)
     * - Applying the `wpaic_rag_pre_chunk` filter for custom pre-processing
     *
     * Returns null if the item has no extractable content (e.g., empty post,
     * excluded post, unpublished status). Returning null causes the queue worker
     * to mark the item as completed with a 'no_content' note.
     *
     * @param string $item_type  The item type constant.
     * @param array  $item_data  The serialized item data from discover_items().
     * @return string|null Clean plain text, or null if no content.
     */
    public function extract_content( $item_type, $item_data );

    /**
     * Return metadata for a content item.
     *
     * Metadata is stored with chunks in `wp_wpaic_chunks.title` and used in
     * retrieval results for citation display.
     *
     * @param string $item_type The item type constant.
     * @param array  $item_data The serialized item data.
     * @return array {
     *     @type string $title      Human-readable item title.
     *     @type string $url        Canonical URL for citation links.
     *     @type string $type       Content type label (used in admin display).
     *     @type int    $post_id    WordPress post ID (0 for non-WP sources).
     * }
     */
    public function get_item_metadata( $item_type, $item_data );
}
```

---

## 3. Adapter Registration

### Hook: `wpaic_register_rag_sources`

Adapters register by calling `$pipeline->register_adapter()` within the
`wpaic_register_rag_sources` action. The hook fires during plugin initialization before any
ingestion operations.

```php
add_action( 'wpaic_register_rag_sources', function ( $pipeline ) {
    $pipeline->register_adapter( new WPAIC\RAG\Adapters\WP_Post_Adapter() );
    $pipeline->register_adapter( new WPAIC\RAG\Adapters\WC_Product_Adapter() );
});
```

The `Ingestion_Pipeline::register_adapter()` method signature:

```php
/**
 * @param Source_Adapter_Interface $adapter
 * @return void
 * @throws \InvalidArgumentException if adapter ID is already registered
 */
public function register_adapter( Source_Adapter_Interface $adapter );
```

### Compliance Rules

| Rule | Check | Failure Behavior |
|------|-------|------------------|
| ACR-01 | Class implements `Source_Adapter_Interface` | Registration rejected, `_doing_it_wrong()` |
| ACR-02 | `get_source_id()` returns non-empty lowercase string | Registration rejected |
| ACR-03 | No duplicate source ID | Registration rejected, first adapter preserved |
| ACR-04 | `get_source_id()` matches at least one entry in `get_supported_item_types()` | Registration rejected |

---

## 4. Built-In Adapters

### MVP Adapter Registry

| Source ID | Class | Item Types | Phase 01 Source |
|-----------|-------|-----------|-----------------|
| `wp_post` | `WP_Post_Adapter` | `wp_post`, `wp_page` | AXI + MXC |
| `wc_product` | `WC_Product_Adapter` | `wc_product` | AXI + MXC |
| `manual_text` | `Manual_Text_Adapter` | `manual_text` | MXC |
| `url` | `URL_Adapter` | `url` | AXI (web scraper) + MXC |
| `sitemap` | `Sitemap_Adapter` | `sitemap` | MXC |
| `pdf` | `PDF_Adapter` | `pdf` | MXC |

**WooCommerce boundary (RES-XD-007)**: `WC_Product_Adapter` indexes product content (title,
description, short description, SKU, categories) as plain text. Real-time price/stock enrichment
at query time is out of scope for RAG pipeline -- deferred to Phase 08.

### WP_Post_Adapter Content Extraction

The `WP_Post_Adapter` applies content cleaning pipeline from AXI pattern:

1. `get_the_content()` with `$post->ID` context
2. Apply `do_shortcode()` for shortcode expansion
3. Elementor content walker: if Elementor active, use `\Elementor\Plugin::instance()->frontend->get_builder_content_for_display()` for rendered markup
4. Strip HTML tags via `wp_strip_all_tags()`
5. Normalize whitespace: collapse multiple spaces/newlines to single space
6. Apply filter `wpaic_rag_pre_chunk` with `$text`, `$post_id`, `$item_type`
7. Return null if `strlen(trim($text)) === 0`

Indexing options (from `wp_wpaic_knowledge.indexing_options` JSON):

| Option | Default | Description |
|--------|---------|-------------|
| `include_excerpt` | `false` | Prepend post excerpt to content |
| `include_url` | `true` | Append canonical URL to text |
| `include_categories` | `false` | Append category names |
| `include_tags` | `false` | Append tag names |
| `include_custom_fields` | `[]` | Array of meta keys to append |
| `include_wc_fields` | `false` | Include WC-specific fields (products only) |

---

## 5. Content Extraction Safety

### Exclusion Controls

Items are excluded from indexing if any of these conditions are true:

1. Post has meta `_wpaic_exclude_from_index` set to `'1'`
2. Post status is not `publish` (drafts, private posts excluded by default unless `include_private` option)
3. Post type not in the adapter's configured post types list
4. Post content after cleaning is empty or whitespace-only

### HTML Sanitization

Content extraction MUST NOT include:
- JavaScript (`<script>` tags and handlers)
- CSS (`<style>` blocks, inline style attributes)
- HTML comments
- Shortcode syntax that was not successfully expanded
- Page builder markup (e.g., `[et_pb_section]`)

Use `wp_strip_all_tags($content, true)` after shortcode expansion to strip residual markup.

### Content Length Limits

| Limit | Value | Behavior on Exceed |
|-------|-------|-------------------|
| Max content length | 500,000 characters | Truncate with warning in queue error_message |
| Min content length | 20 characters | Skip with 'no_content' status note |

---

## 6. Pre-Chunk Filter Hook

### `wpaic_rag_pre_chunk`

Fires after content extraction, before chunking. Allows external code to modify, filter, or
append content before it is split into chunks.

```php
/**
 * @param string $text        The extracted content text.
 * @param int    $post_id     WordPress post ID (0 for non-WP sources).
 * @param string $item_type   The item type constant.
 * @return string Modified content text.
 */
$text = apply_filters( 'wpaic_rag_pre_chunk', $text, $post_id, $item_type );
```

**Contract**: Filter MUST return a string. Returning empty string is valid and causes the item
to be skipped with `no_content` status. Filter MUST NOT throw exceptions.

---

## 7. File and Class Naming Conventions

Per session 01 module map:

| Component | Location | Naming Pattern |
|-----------|----------|----------------|
| Interface | `includes/rag/interface-source-adapter.php` | `Source_Adapter_Interface` |
| Adapters dir | `includes/rag/adapters/` | |
| WP Posts | `includes/rag/adapters/class-wp-post-adapter.php` | `WPAIC\RAG\Adapters\WP_Post_Adapter` |
| WC Products | `includes/rag/adapters/class-wc-product-adapter.php` | `WPAIC\RAG\Adapters\WC_Product_Adapter` |
| Manual Text | `includes/rag/adapters/class-manual-text-adapter.php` | `WPAIC\RAG\Adapters\Manual_Text_Adapter` |
| URL | `includes/rag/adapters/class-url-adapter.php` | `WPAIC\RAG\Adapters\URL_Adapter` |
| Sitemap | `includes/rag/adapters/class-sitemap-adapter.php` | `WPAIC\RAG\Adapters\Sitemap_Adapter` |
| PDF | `includes/rag/adapters/class-pdf-adapter.php` | `WPAIC\RAG\Adapters\PDF_Adapter` |

---

## 8. Source Anchor Comparison

| Capability | AxiaChat | MxChat | Baachal | WPAIC |
|-----------|----------|--------|---------|-------|
| Source registration | Filter hook `aichat_generate_embedding_override` | Dispatch by item_type in monolithic class | Hardcoded post types | Registry via `wpaic_register_rag_sources` hook |
| Source breadth | Posts, pages, products, web, files | Posts, pages, products, URLs, sitemaps, PDFs, ACF, bbPress | Posts, pages only | All MXC sources via adapters |
| Content cleaning | Elementor walker, shortcode expansion | UTF-8 normalization, control char removal | wp_strip_all_tags | AXI cleaning pipeline |
| Post exclusion | `_aichat_exclude` meta | N/A | `_baachal_exclude` meta | `_wpaic_exclude_from_index` meta |
