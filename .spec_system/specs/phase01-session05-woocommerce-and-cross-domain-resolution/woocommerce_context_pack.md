# WooCommerce Domain Context Pack

**Domain**: DOM-WOOCOMMERCE
**Session**: phase01-session05-woocommerce-and-cross-domain-resolution
**Sources**: SRC-AXI (AxiaChat), SRC-MXC (MxChat), SRC-BAA (Baachal)
**Created**: 2026-03-01

---

## Context Pack Summary

| Source | Primary WooCommerce Files | Approach |
|--------|--------------------------|----------|
| SRC-AXI | `contexto-functions.php`, `training-context.php` | RAG-based: product indexing at content ingestion + query-time enrichment with live WC data |
| SRC-MXC | `class-mxchat-woocommerce.php`, `class-mxchat-integrator.php` | Direct WC API: cart ops, order history, product message extraction, vector product search |
| SRC-BAA | `baachal.php`, `admin/tabs/woocommerce.php`, `admin/tabs/search.php` | Keyword-based: multi-stage product search, dynamic term extraction, product card rendering |

---

## SRC-AXI Excerpts

### ANC-WOO-AXI-CTXFN: Product Indexing Configuration
**File**: `axiachat-ai/includes/contexto-functions.php` (lines 185-210, 464-491)

```php
// Default indexing options include WooCommerce-specific fields
function aichat_default_indexing_options() {
    return [
        // ...standard options...
        'include_wc_short_description' => true,
        'include_wc_attributes'        => true,
        'include_meta_fields'          => ['_price', '_sku', '_stock_status'],
    ];
}

// WooCommerce static fields (only for products)
if ($post->post_type === 'product' && class_exists('WooCommerce')
    && function_exists('wc_get_product')) {
    $product = wc_get_product($post->ID);
    if ($product) {
        // Short description
        if (! empty($opts['include_wc_short_description'])) {
            $short = wp_strip_all_tags($product->get_short_description());
            if ($short !== '') {
                $parts[] = $short;
            }
        }
        // Attributes (static: sizes, colors, materials, etc.)
        if (! empty($opts['include_wc_attributes'])) {
            $attributes = $product->get_attributes();
            foreach ($attributes as $attr) {
                if (is_a($attr, 'WC_Product_Attribute')) {
                    $attr_label = wc_attribute_label($attr->get_name());
                    $attr_values = $attr->is_taxonomy()
                        ? wp_list_pluck($attr->get_terms(), 'name')
                        : $attr->get_options();
                    if (! empty($attr_values)) {
                        $parts[] = $attr_label . ': ' . implode(', ', $attr_values);
                    }
                }
            }
        }
    }
}
```

### ANC-WOO-AXI-ENRICH: Query-Time Product Data Enrichment
**File**: `axiachat-ai/includes/contexto-functions.php` (lines 520-567)

```php
/**
 * Enrich context results at query time with fresh WooCommerce dynamic data.
 * Only applies to rows where type === 'product' and WooCommerce is active.
 */
function aichat_enrich_context_with_product_data($results)
{
    if (! class_exists('WooCommerce') || ! function_exists('wc_get_product')) {
        return $results;
    }
    foreach ($results as &$row) {
        if (($row['type'] ?? '') !== 'product') {
            continue;
        }
        $product = wc_get_product($row['post_id'] ?? 0);
        if (! $product) {
            continue;
        }
        $extras = [];
        // Price (with sale detection)
        $price = $product->get_price();
        if ($price !== '' && $price !== null) {
            $currency = function_exists('get_woocommerce_currency_symbol')
                ? get_woocommerce_currency_symbol() : 'EUR';
            $extras[] = __('Price', 'axiachat-ai') . ': ' . $price . $currency;
            if ($product->is_on_sale()) {
                $regular = $product->get_regular_price();
                $extras[] = __('Regular price', 'axiachat-ai') . ': ' . $regular . $currency;
            }
        }
        // SKU
        $sku = $product->get_sku();
        if ($sku) {
            $extras[] = 'SKU: ' . $sku;
        }
        // Stock status
        $stock = $product->get_stock_status();
        if ($stock) {
            $stock_labels = [
                'instock'     => __('In stock', 'axiachat-ai'),
                'outofstock'  => __('Out of stock', 'axiachat-ai'),
                'onbackorder' => __('On backorder', 'axiachat-ai'),
            ];
            $extras[] = __('Stock', 'axiachat-ai') . ': ' . ($stock_labels[$stock] ?? $stock);
        }
        if (! empty($extras)) {
            $row['content'] .= "\n" . implode("\n", $extras);
        }
    }
    unset($row);
    return $results;
}
```

### ANC-WOO-AXI-TRAIN: WooCommerce Admin Training UI
**File**: `axiachat-ai/includes/training-context.php` (lines 222-263)

```php
<!-- WooCommerce source card: product selection modes for indexing -->
<div class="aichat-source-card" data-source="woocommerce"
     data-has-woo="<?php echo esc_attr($has_woocommerce); ?>">
    <h4>WooCommerce Products</h4>
    <select name="woo_mode">
        <option value="none">None</option>
        <option value="all">All Products</option>
        <option value="recent">Most Recent</option>
        <option value="search">Search</option>
    </select>
    <!-- Product search input, advanced WC indexing options:
         short description, attributes checkboxes -->
</div>
```

### ANC-WOO-AXI-001: Appointments Manager (WooCommerce-adjacent)
**File**: `axiachat-ai/includes/add-ons/appointments/class-appointments-manager.php` (1047 lines)

Key patterns (bounded summary -- not a direct WC integration but demonstrates commerce workflow):
- Adapter-based booking system (internal, bookly, amelia, ssa, google_calendar)
- DB-backed CRUD with custom table `wp_aichat_appointments`
- Freemium gating: 50 free bookings, Feature ID 33019 for unlimited
- Email templates with placeholders, cron-based reminders
- Webhook POST notifications (PRO-only)

### ANC-WOO-AXI-002: Leads Manager (WooCommerce-adjacent)
**File**: `axiachat-ai/includes/add-ons/leads/class-leads-manager.php` (850 lines)

Key patterns (bounded summary -- commerce lead capture, not direct WC):
- Multi-destination: internal DB, Google Sheets, CPT, CF7, WPForms
- Lead states: nuevo/contactado/convertido/descartado
- GDPR: IP hashing via `wp_salt()`
- Freemium: 50 free leads, Feature ID 26990 for unlimited
- CSV export, retention cleanup via cron

---

## SRC-MXC Excerpts

### ANC-WOO-MXC-001: Dedicated WooCommerce Class
**File**: `mxchat-basic/includes/class-mxchat-woocommerce.php` (253 lines, complete)

```php
class MxChat_WooCommerce
{
    public static function init()
    {
        add_action('wp_ajax_mxchat_fetch_user_orders',
            [__CLASS__, 'mxchat_fetch_user_orders']);
        add_action('wp_ajax_nopriv_mxchat_fetch_user_orders',
            [__CLASS__, 'mxchat_fetch_user_orders']);
        add_action('wp_ajax_mxchat_add_to_cart',
            [__CLASS__, 'mxchat_add_to_cart']);
        add_action('wp_ajax_nopriv_mxchat_add_to_cart',
            [__CLASS__, 'mxchat_add_to_cart']);
    }

    // Add to Cart -- nonce validated, returns product name confirmation
    public static function mxchat_add_to_cart()
    {
        if (!isset($_POST['nonce'])
            || !wp_verify_nonce($_POST['nonce'], 'mxchat_nonce')) {
            wp_send_json_error('Invalid nonce.');
            wp_die();
        }
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        if (!$product_id) {
            wp_send_json_error('Invalid product ID.');
            wp_die();
        }
        $added = WC()->cart->add_to_cart($product_id);
        if ($added) {
            $product = wc_get_product($product_id);
            wp_send_json_success([
                'message' => "The product '{$product->get_name()}'
                              has been added to your cart."
            ]);
        } else {
            wp_send_json_error('Error adding product to cart.');
        }
        wp_die();
    }

    // Order History -- user-bound, supports 'all' (5) or 'last' (1)
    public static function mxchat_fetch_user_orders_details($type = 'all')
    {
        $user_id = get_current_user_id();
        if (!$user_id && isset(WC()->session)) {
            $user_id = WC()->session->get_customer_id();
        }
        if (!$user_id) {
            return [];
        }
        $args = [
            'customer_id' => $user_id,
            'limit'       => ($type === 'last') ? 1 : 5,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ];
        $orders = wc_get_orders($args);
        // Returns: order_id, date, status, formatted_total, items[]
        // Items include: name, line subtotal, license_key meta, quantity
    }

    // Cart check
    public static function cart_has_items()
    {
        return WC()->cart && WC()->cart->get_cart_contents_count() > 0;
    }

    // Product extraction from chat message -- fuzzy matching
    public static function mxchat_extract_product_id_from_message($message)
    {
        // Fetches ALL published products via wc_get_products()
        // Normalized text comparison: name, slug, SKU
        // Scoring: matched terms ratio + consecutive match bonus (+0.3)
        //          + exact phrase bonus (+0.5)
        // Stop words filtered: the, and, or, for, to, in, on, at, of
        // Returns best match product ID or null
    }

    // Product context tracking via transients (12-hour TTL)
    public static function store_last_discussed_product($product_id)
    {
        set_transient('mxchat_last_product', $product_id, 12 * HOUR_IN_SECONDS);
    }
    public static function get_last_discussed_product()
    {
        return get_transient('mxchat_last_product');
    }
}
MxChat_WooCommerce::init();
```

### ANC-WOO-MXC-002: Integrator Vector Product Search
**File**: `mxchat-basic/includes/class-mxchat-integrator.php` (lines 5923-6120)

```php
// Dispatch: Pinecone or WordPress DB based on addon settings
public function mxchat_find_relevant_products($user_embedding) {
    // Routes to find_relevant_products_wordpress() or
    // find_relevant_products_pinecone() based on config
}

// WordPress DB vector search
public function find_relevant_products_wordpress($user_embedding) {
    // Queries wp_mxchat_system_prompt_content table
    // Cosine similarity with 0.85 threshold
    // Top 3 results, fetches content with product links
}

// Pinecone vector search
public function find_relevant_products_pinecone($user_embedding) {
    // Queries Pinecone API with type: 'product' filter
    // 0.85 similarity threshold
    // Returns metadata text and source_url
}

// Fetch content with product links
public function fetch_content_with_product_links($id) {
    // Gets article_content + source_url from system prompt table
    // Appends product link
}
```

### ANC-WOO-MXC-ADDON: WooCommerce PRO Add-on Definition
**File**: `mxchat-basic/includes/class-mxchat-addons.php` (lines 168-181)

```php
// mxchat-woo add-on definition (PRO-only)
'mxchat-woo' => [
    'title'       => 'MxChat WooCommerce',
    'description' => 'Boost sales with AI-powered shopping assistance',
    'benefits'    => [
        'Smart product recommendations',
        'Cart & checkout assistance',
        'Order history access',
    ],
    'license'     => 'MxChat PRO',
    'plugin_file' => 'mxchat-woo/mxchat-woo.php',
],
// PRO callbacks: mxchat_handle_product_recommendations,
//   mxchat_handle_order_history, mxchat_show_product_card,
//   mxchat_add_to_cart, mxchat_checkout_redirect
```

### ANC-WOO-MXC-ADMIN: WooCommerce Admin Settings
**File**: `mxchat-basic/includes/class-mxchat-admin.php` (lines 166, 8092-8097, 8465)

```php
// Default: WooCommerce integration off by default
'enable_woocommerce_integration' => '0',

// Sanitizes WooCommerce consumer key/secret for REST API access
// Keys marked for API key masking in admin UI
```

---

## SRC-BAA Excerpts

### ANC-WOO-BAA-001: WooCommerce Admin Tab
**File**: `baachal/admin/tabs/woocommerce.php` (54 lines, complete)

```php
<!-- WooCommerce detection with product count display -->
<?php if (class_exists('WooCommerce')): ?>
    <p><strong>WooCommerce Detected!</strong>
       Found <?php echo wp_count_posts('product')->publish; ?> published products.</p>
<?php else: ?>
    <p><strong>WooCommerce Not Detected!</strong>
       Install and activate WooCommerce to enable product integration.</p>
<?php endif; ?>

<!-- Settings: enable toggle (disabled without WC), message persistence,
     clear history button -->
<input type="checkbox" name="baachal_woocommerce_integration" value="1"
       <?php checked($woocommerce_integration, '1'); ?>
       <?php echo !class_exists('WooCommerce') ? 'disabled' : ''; ?> />
```

### ANC-WOO-BAA-002: Product Context Pipeline
**File**: `baachal/baachal.php` (lines 1376-1474)

```php
private function get_website_context($user_message = '')
{
    // Builds system prompt with site info
    // Injects WooCommerce context when WC active + integration enabled:
    if (class_exists('WooCommerce')
        && get_option('baachal_woocommerce_integration', '1') === '1') {
        $woocommerce_context = $this->get_woocommerce_context($user_message);
        if (!empty($woocommerce_context)) {
            $context .= $woocommerce_context;
        }
    }
    // Adds product recommendation instructions to AI
}

private function get_woocommerce_context($user_message = '')
{
    // If user message provided: get_relevant_products() -> format_products_context()
    // Fallback: get_sample_products_context()
}

private function get_relevant_products($user_message, $limit = 5)
{
    // Transient-cached product search (1-hour TTL)
    // Cache key: md5(lowercase message) + limit
    // Calls search_products_by_keywords(), fills with popular products
}
```

### ANC-WOO-BAA-SEARCH: Multi-Stage Product Search
**File**: `baachal/baachal.php` (lines 1476-1568)

```php
private function search_products_by_keywords($user_message, $limit = 5)
{
    $keywords = $this->extract_keywords_improved($user_message);
    // Stage 1: Exact phrase search in titles
    $exact_products = $this->search_by_exact_phrase($user_message, $limit);
    // Stage 2: Weighted keyword search (title 2x, content 1x)
    $keyword_products = $this->search_by_weighted_keywords($keywords, ...);
    // Stage 3: Product attribute search (meta_key LIKE 'attribute_%')
    $attribute_products = $this->search_by_attributes($keywords, ...);
    // Stage 4: Taxonomy search (product_cat, product_tag)
    $taxonomy_products = $this->search_by_taxonomies($keywords, ...);
    // Deduplication by post ID
}

private function extract_keywords_improved($message)
{
    // Dynamic important terms from store catalog
    $important_terms = $this->get_dynamic_product_terms();
    // Multi-word term extraction first
    // Stop words filtering (60+ words)
    // Returns merged unique keywords
}

private function get_dynamic_product_terms()
{
    // Transient-cached extraction from categories, attributes, tags
    // Title phrase splitting
    // Configurable: max_terms, min_term_length, exclude_terms
}
```

### ANC-WOO-BAA-FORMAT: Product Context Formatting and Card Rendering
**File**: `baachal/baachal.php` (lines 2033-2227)

```php
private function format_products_context($products, $render_cards = true)
{
    // For each product: WC API data extraction
    // price_html, price_text (fallback chain), categories, attributes,
    // stock_status (with quantity when managing_stock), description (30 words),
    // image_url
    // Builds AI context: "This is a WooCommerce online store..."
    // Product lines: name | price | category | attributes | URL | description | stock
    // Recommendation instructions for AI (use exact names, concise intro,
    //   product cards auto-appended, focus on relevant attributes)
    // Filter: 'baachal_product_card_data'
}

private function build_product_cards_html($cards)
{
    // Renders <div class="baachal-product-grid">
    // Per product: <article class="baachal-product-card">
    //   Link (a or div), image (with placeholder fallback),
    //   title (h4), price (wp_kses_post or esc_html)
    // Filter: 'baachal_product_cards_html'
}
```

### ANC-WOO-BAA-HPOS: WooCommerce HPOS Compatibility
**File**: `baachal/baachal.php` (lines 38-43, 89-126)

```php
// HPOS compatibility declaration
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Product cache invalidation hooks:
// save_post, delete_post, wp_trash_post, untrash_post -> clear product transients
// woocommerce_attribute_added/updated/deleted -> clear dynamic terms cache
```

### ANC-WOO-BAA-SETTINGS: Product Search Configuration
**File**: `baachal/admin/tabs/search.php` (94 lines) + `baachal/baachal.php` (lines 2480-2518)

```php
// Registered settings:
// baachal_woocommerce_integration (checkbox, default '1')
// baachal_max_terms (integer, default 100)
// baachal_min_term_length (integer, default 3)
// baachal_cache_duration (seconds, default 3600)
// baachal_exclude_terms (comma-separated, default '')
// Admin UI: cache management, documentation of dynamic term extraction
```

---

## Cross-Source Comparison Summary

| Capability | SRC-AXI | SRC-MXC | SRC-BAA |
|-----------|---------|---------|---------|
| Product context injection | RAG-based indexing + query-time enrichment | Vector similarity search (DB/Pinecone) | Keyword multi-stage search + AI context formatting |
| Cart operations | N/A | Add to cart (AJAX, nonce-validated) | N/A |
| Order history | N/A | User-bound order fetch (last 1 or 5) | N/A |
| Product search from chat | N/A | Fuzzy message-to-product matching | 4-stage keyword search with dynamic terms |
| Product card rendering | N/A | productCardHtml in response JSON | Product grid HTML with images, price, link |
| Catalog operations | Product indexing with WC fields | Product extraction from messages | Multi-stage search (exact, weighted, attribute, taxonomy) |
| WC data freshness | Query-time enrichment (price/stock/SKU live) | Index-time only | Query-time via WC API |
| HPOS compatibility | Not declared | Not declared | Declared |
| Admin configuration | Indexing options (short desc, attributes, meta) | Enable toggle, consumer key/secret | Enable toggle, search tuning (max_terms, cache, excludes) |
| Freemium/licensing | Add-on features gated by license tiers | WooCommerce as PRO add-on (5 callbacks) | Free, no gating |
| Cache strategy | RAG index (persistent) | Transient (12h product tracking) | Transient (1h search results, dynamic terms) |

---

## Token Budget Assessment

| Source | Excerpt Lines | Estimated Tokens |
|--------|--------------|-----------------|
| SRC-AXI | ~120 | ~4,800 |
| SRC-MXC | ~140 | ~5,600 |
| SRC-BAA | ~160 | ~6,400 |
| **Total** | **~420** | **~16,800** |

Within 48k target budget. Context pack is bounded and anchored.
