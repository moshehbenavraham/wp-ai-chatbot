# WooCommerce Feature Entries

**Domain**: DOM-WOOCOMMERCE
**Session**: phase01-session05-woocommerce-and-cross-domain-resolution
**Sources**: SRC-AXI (AxiaChat), SRC-MXC (MxChat), SRC-BAA (Baachal)
**Created**: 2026-03-01

---

## Feature Entry Format

Each entry follows the schema established in sessions 01-04:
- **ID**: `FEAT-{SRC}-WOO-{NNN}`
- **Capability Group**: Functional grouping within WooCommerce domain
- **Anchor**: Source file reference with ANC-* ID
- **Evidence**: Specific code patterns and line references

---

## Capability Group 1: Product Context Injection

How each source provides product information to the AI chat context.

### FEAT-AXI-WOO-001: RAG-Based Product Indexing
**Anchor**: ANC-WOO-AXI-CTXFN (`axiachat-ai/includes/contexto-functions.php:464-491`)
**Description**: Indexes WooCommerce products into RAG vector store at ingestion time. Configurable inclusion of short description and product attributes (via `WC_Product_Attribute` objects with taxonomy term resolution). Products become part of the standard RAG retrieval pipeline.
**Evidence**:
- `include_wc_short_description` and `include_wc_attributes` indexing options
- Attribute extraction handles both taxonomy and custom attributes
- Products indexed alongside other content types (pages, posts)
**Boundary Note**: Product indexing is a RAG pipeline extension, not a standalone WooCommerce feature. Classified under WooCommerce because it handles WC-specific data models.

### FEAT-AXI-WOO-002: Query-Time Product Data Enrichment
**Anchor**: ANC-WOO-AXI-ENRICH (`axiachat-ai/includes/contexto-functions.php:520-567`)
**Description**: Enriches RAG retrieval results with live WooCommerce data at query time. Applies only to results with `type === 'product'`. Injects current price (with sale detection), SKU, and stock status (instock/outofstock/onbackorder) into context content.
**Evidence**:
- `aichat_enrich_context_with_product_data()` function
- Sale price detection via `$product->is_on_sale()`
- Currency symbol via `get_woocommerce_currency_symbol()`
- Stock status with localized labels
- Appends to `$row['content']` at query time for freshness
**Strength**: Separates static (index-time) from dynamic (query-time) product data -- ensures price and stock are always current without re-indexing.

### FEAT-MXC-WOO-001: Vector Product Search
**Anchor**: ANC-WOO-MXC-002 (`mxchat-basic/includes/class-mxchat-integrator.php:5923-6120`)
**Description**: Finds relevant products via vector similarity search. Supports two backends: WordPress DB (cosine similarity against `wp_mxchat_system_prompt_content` table) and Pinecone (API query with `type: product` filter). Both use 0.85 similarity threshold, top 3 results.
**Evidence**:
- `mxchat_find_relevant_products()` dispatcher
- `find_relevant_products_wordpress()` -- brute-force cosine similarity, 0.85 threshold
- `find_relevant_products_pinecone()` -- Pinecone API with metadata filter
- `fetch_content_with_product_links()` -- appends source URL to content
**Boundary Note**: Uses same vector infrastructure as RAG domain. Product search is vector-based but products are not indexed with WC-specific fields (no price/stock enrichment).

### FEAT-BAA-WOO-001: Multi-Stage Keyword Product Search
**Anchor**: ANC-WOO-BAA-SEARCH (`baachal/baachal.php:1476-1568`)
**Description**: 4-stage keyword-based product search pipeline:
1. Exact phrase search in titles
2. Weighted keyword search (title 2x score, content 1x)
3. Product attribute search (`meta_key LIKE 'attribute_%'`)
4. Taxonomy search (`product_cat`, `product_tag`)
Deduplication by post ID. Results cached via transients (configurable TTL).
**Evidence**:
- `search_products_by_keywords()` 4-stage pipeline
- `extract_keywords_improved()` with dynamic store-aware terms
- `get_dynamic_product_terms()` extracts terms from categories, attributes, tags
- Configurable: max_terms, min_term_length, cache_duration, exclude_terms
**Strength**: No vector/embedding dependency. Works on any WordPress install without external services. Attribute and taxonomy search covers structured product data.

### FEAT-BAA-WOO-002: AI Context Formatting with Product Data
**Anchor**: ANC-WOO-BAA-FORMAT (`baachal/baachal.php:2033-2169`)
**Description**: Formats product search results into structured AI context. Extracts comprehensive product data via WC API: price (with HTML/text fallback chain), categories, attributes, stock status (with quantity when managing stock), short description (30-word trim), image URL. Includes AI recommendation instructions.
**Evidence**:
- `format_products_context()` -- full WC product data extraction
- Price fallback: `get_price_html()` -> `wc_price()` -> raw price
- Stock: instock with quantity, outofstock, onbackorder
- AI instructions: use exact names, concise intro, cards auto-appended
- Filter: `baachal_product_card_data` for extension
**Strength**: Most comprehensive product data formatting for AI consumption across all sources.

---

## Capability Group 2: Catalog Operations

Direct WooCommerce catalog query and manipulation capabilities.

### FEAT-AXI-WOO-003: Product Indexing Configuration UI
**Anchor**: ANC-WOO-AXI-TRAIN (`axiachat-ai/includes/training-context.php:222-263`)
**Description**: Admin training UI with WooCommerce source card. Offers product selection modes: None, All Products, Most Recent, Search. Advanced indexing options for WC-specific fields (short description, attributes). WooCommerce detection via `data-has-woo` attribute.
**Evidence**:
- WooCommerce source card with 4 selection modes
- Product search input field
- Conditional WC-specific indexing option checkboxes
- `$has_woocommerce = class_exists('WooCommerce') ? 1 : 0`

### FEAT-MXC-WOO-002: Product Extraction from Chat Messages
**Anchor**: ANC-WOO-MXC-001 (`mxchat-basic/includes/class-mxchat-woocommerce.php:112-234`)
**Description**: Extracts product ID from natural language chat messages using fuzzy text matching. Fetches all published products via `wc_get_products()`, normalizes text, scores matches across product name, slug, and SKU. Scoring includes matched terms ratio, consecutive match bonus (+0.3), and exact phrase bonus (+0.5). Stop words filtered.
**Evidence**:
- `mxchat_extract_product_id_from_message()` -- complete matching algorithm
- All published products loaded per query (no caching, no limit)
- 9 stop words: the, and, or, for, to, in, on, at, of
- Sorted by score descending, tiebreak by consecutive matches
**Weakness**: Loads ALL products on every extraction attempt (`'limit' => -1`). O(n) per message on product catalog. No caching. Verbose `error_log()` calls in production code.

### FEAT-BAA-WOO-003: Dynamic Product Term Extraction
**Anchor**: ANC-WOO-BAA-SEARCH (`baachal/baachal.php:1570-1628`)
**Description**: Automatically builds search vocabulary from store catalog. Extracts terms from product categories, attributes, tags, and title phrases. Cached via transients. Configurable max_terms, min_term_length, exclude_terms.
**Evidence**:
- `get_dynamic_product_terms()` with transient caching
- Multi-word term extraction from product titles
- Category, attribute, and tag term collection
- Admin-configurable term limits and exclusions

### FEAT-BAA-WOO-004: WooCommerce Admin Configuration
**Anchor**: ANC-WOO-BAA-001 (`baachal/admin/tabs/woocommerce.php`, `baachal/admin/tabs/search.php`)
**Description**: Admin settings for WooCommerce integration: enable toggle (disabled without WC), product count display, message persistence, clear history button. Separate search configuration tab with max_terms, min_term_length, cache_duration, exclude_terms settings plus cache management UI.
**Evidence**:
- WooCommerce detection with product count
- 6 registered settings (integration, max_terms, min_term_length, cache_duration, exclude_terms, plus message persistence)
- Cache management interface

---

## Capability Group 3: Cart and Order Handling

Direct WooCommerce cart and order operations from chat.

### FEAT-AXI-WOO-N/A-001: Cart Operations
**Source**: SRC-AXI
**Status**: N/A
**Rationale**: AxiaChat has no direct cart manipulation capabilities. WooCommerce-adjacent features (appointments, leads) do not interact with WC cart or checkout. Product context is read-only via RAG enrichment.

### FEAT-MXC-WOO-003: Add to Cart via Chat
**Anchor**: ANC-WOO-MXC-001 (`mxchat-basic/includes/class-mxchat-woocommerce.php:20-43`)
**Description**: AJAX endpoint for adding products to WooCommerce cart from chat interface. Validates nonce, sanitizes product ID via `intval()`, calls `WC()->cart->add_to_cart()`, returns product name confirmation.
**Evidence**:
- `mxchat_add_to_cart()` AJAX handler (wp_ajax + wp_ajax_nopriv)
- Nonce validation: `wp_verify_nonce($_POST['nonce'], 'mxchat_nonce')`
- Product ID sanitization: `intval($_POST['product_id'])`
- Success returns product name; failure returns generic error
**Weakness**: No capability check for cart operations. No product existence/visibility validation before `add_to_cart()`. No quantity support. No variable product/variation support.

### FEAT-MXC-WOO-004: Order History Retrieval
**Anchor**: ANC-WOO-MXC-001 (`mxchat-basic/includes/class-mxchat-woocommerce.php:46-97`)
**Description**: Retrieves order history for authenticated users. Supports 'all' (last 5 orders) or 'last' (most recent). Uses `wc_get_orders()` with customer_id binding. Falls back to WC session customer ID for guest checkout. Returns order details including line items with license key metadata.
**Evidence**:
- `mxchat_fetch_user_orders_details($type)` method
- User binding: `get_current_user_id()` with `WC()->session->get_customer_id()` fallback
- Order data: id, date, status name, formatted total, items (name, subtotal, license_key, quantity)
**Strength**: Only source with order history access. User binding is secure (no arbitrary user lookups).

### FEAT-MXC-WOO-005: Cart Status Check
**Anchor**: ANC-WOO-MXC-001 (`mxchat-basic/includes/class-mxchat-woocommerce.php:101-104`)
**Description**: Simple cart contents check via `WC()->cart->get_cart_contents_count()`.
**Evidence**: `cart_has_items()` static method. Returns boolean.

### FEAT-BAA-WOO-N/A-001: Cart Operations
**Source**: SRC-BAA
**Status**: N/A
**Rationale**: Baachal has no cart manipulation or order handling capabilities. WooCommerce integration is limited to product context injection for AI responses.

---

## Capability Group 4: Product Presentation and Recommendations

How products are visually presented to users in chat.

### FEAT-AXI-WOO-N/A-002: Product Card Rendering
**Source**: SRC-AXI
**Status**: N/A
**Rationale**: AxiaChat does not render product cards in chat. Product data flows through RAG context as plain text. No visual product presentation layer.

### FEAT-MXC-WOO-006: Product Card HTML in Responses
**Anchor**: ANC-WOO-MXC-002 (`mxchat-basic/includes/class-mxchat-integrator.php:12,1891-1907`)
**Description**: Stores product card HTML per request cycle (`$productCardHtml` property). After AI response, saves as separate bot message. Included in response JSON as `html` field for frontend rendering.
**Evidence**:
- `$this->productCardHtml` property reset per request (line 1131)
- Saved as separate message after AI response (line 1891-1892)
- Included in JSON response as `html` key (line 1907)
**Boundary Note**: Card rendering itself is in the PRO add-on (`mxchat-woo/mxchat-woo.php`), not in the base plugin.

### FEAT-MXC-WOO-007: Product Context Tracking
**Anchor**: ANC-WOO-MXC-001 (`mxchat-basic/includes/class-mxchat-woocommerce.php:239-248`)
**Description**: Tracks last discussed product via WP transients (12-hour TTL). Enables contextual follow-up questions about previously mentioned products.
**Evidence**:
- `store_last_discussed_product($product_id)` -- `set_transient('mxchat_last_product', $product_id, 12 * HOUR_IN_SECONDS)`
- `get_last_discussed_product()` -- `get_transient('mxchat_last_product')`
**Weakness**: Global transient (not per-session). All users share the same "last product" context. Race condition in concurrent sessions.

### FEAT-BAA-WOO-005: Product Card Grid Rendering
**Anchor**: ANC-WOO-BAA-FORMAT (`baachal/baachal.php:2172-2227`)
**Description**: Renders product card grid HTML. Semantic markup with `<article>` cards containing image (with placeholder fallback), title, and price. Links open in new tab with `rel="noopener"`. Output escaped via `esc_url()`, `esc_attr()`, `esc_html()`, `wp_kses_post()`.
**Evidence**:
- `build_product_cards_html()` method
- `<div class="baachal-product-grid">` container
- Per-product: `<article class="baachal-product-card">` with image, title, price
- Filter: `baachal_product_cards_html`
**Strength**: Most complete card rendering: semantic HTML, accessibility considerations (alt text, link attrs), proper escaping, extensible via filter.

---

## Capability Group 5: WooCommerce Platform Compatibility

Technical compatibility with WooCommerce platform features.

### FEAT-AXI-WOO-N/A-003: HPOS Compatibility
**Source**: SRC-AXI
**Status**: N/A
**Rationale**: AxiaChat does not declare WooCommerce HPOS (High-Performance Order Storage) compatibility. No WC order table interactions to require it.

### FEAT-MXC-WOO-N/A-001: HPOS Compatibility
**Source**: SRC-MXC
**Status**: N/A
**Rationale**: MxChat does not declare HPOS compatibility despite accessing orders via `wc_get_orders()`. Uses WC CRUD API which is HPOS-safe, but no formal declaration.

### FEAT-BAA-WOO-006: HPOS Compatibility Declaration
**Anchor**: ANC-WOO-BAA-HPOS (`baachal/baachal.php:38-43`)
**Description**: Declares WooCommerce High-Performance Order Storage compatibility via `FeaturesUtil::declare_compatibility('custom_order_tables')`. Hooks into `before_woocommerce_init`.
**Evidence**:
- `FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true)`
- Proper timing via `before_woocommerce_init` hook
**Strength**: Only source with formal HPOS declaration. Required for WC 9.0+ compatibility.

### FEAT-BAA-WOO-007: Product Cache Invalidation
**Anchor**: ANC-WOO-BAA-HPOS (`baachal/baachal.php:89-126`)
**Description**: Hooks into WordPress post lifecycle (save, delete, trash, untrash) to clear product transient caches. Also hooks WooCommerce attribute events (added/updated/deleted) to clear dynamic terms cache.
**Evidence**:
- Post hooks: `save_post`, `delete_post`, `wp_trash_post`, `untrash_post`
- WC hooks: `woocommerce_attribute_added`, `woocommerce_attribute_updated`, `woocommerce_attribute_deleted`
- Clears: product search transients and dynamic terms transient
**Strength**: Only source with cache invalidation on product/attribute changes. Prevents stale product data in search results.

---

## Feature Entry Summary

| Capability Group | SRC-AXI | SRC-MXC | SRC-BAA | Total |
|-----------------|---------|---------|---------|-------|
| Product Context Injection | 2 | 1 | 2 | 5 |
| Catalog Operations | 1 | 1 | 2 | 4 |
| Cart and Order Handling | N/A | 3 | N/A | 3 |
| Product Presentation | N/A | 2 | 1 | 3 |
| WC Platform Compatibility | N/A | N/A | 2 | 2 |
| **Total Features** | **3** | **7** | **7** | **17** |
| **N/A Entries** | **3** | **1** | **1** | **5** |

**ID Registry**:
- FEAT-AXI-WOO-001 through FEAT-AXI-WOO-003 (3 features + 3 N/A)
- FEAT-MXC-WOO-001 through FEAT-MXC-WOO-007 (7 features + 1 N/A)
- FEAT-BAA-WOO-001 through FEAT-BAA-WOO-007 (7 features + 1 N/A)
