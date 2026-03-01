# WooCommerce Scored Comparison

**Domain**: DOM-WOOCOMMERCE
**Session**: phase01-session05-woocommerce-and-cross-domain-resolution
**Scoring Framework**: 5-dimension weighted rubric from session 01
**Created**: 2026-03-01

---

## Scoring Scale

| Score | Meaning |
|-------|---------|
| 0 | Not present / unusable |
| 1 | Bare-minimum |
| 2 | Partial with constraints |
| 3 | Solid for common cases |
| 4 | Strong with low friction |
| 5 | Best-in-class |

## Dimension Weights

| Dimension | Weight | ID |
|-----------|--------|----|
| Coverage | 30% | DIM-COVERAGE |
| Reliability | 20% | DIM-RELIABILITY |
| Security | 20% | DIM-SECURITY |
| Operability | 15% | DIM-OPERABILITY |
| Extensibility | 15% | DIM-EXTENSIBILITY |

**Weighted Total**: `(COV * 0.30) + (REL * 0.20) + (SEC * 0.20) + (OPR * 0.15) + (EXT * 0.15)`

---

## Capability 1: Product Context Injection

How each source provides product information to the AI chat context.

### SRC-AXI: RAG-Based Product Indexing + Query-Time Enrichment

| Dimension | Score | Evidence |
|-----------|-------|----------|
| DIM-COVERAGE | 4 | Indexes products via RAG pipeline with configurable WC fields (short desc, attributes). Query-time enrichment adds live price/stock/SKU. Missing: no standalone product search outside RAG context. |
| DIM-RELIABILITY | 4 | Split static/dynamic data architecture ensures price and stock are always current. WC class existence checks. Graceful fallback when WC inactive. |
| DIM-SECURITY | 4 | Data flows through RAG sanitization pipeline. `wp_strip_all_tags()` on all indexed fields. `get_woocommerce_currency_symbol()` safe call. No user input in enrichment path. |
| DIM-OPERABILITY | 3 | Requires RAG indexing setup (not zero-config). Admin must configure product source in training UI. No standalone WC product context without RAG. |
| DIM-EXTENSIBILITY | 4 | `aichat_index_text_parts` filter for custom indexing. WC-specific indexing options configurable. Enrichment function composable with other post-retrieval hooks. |

**Weighted Total**: (4 * 0.30) + (4 * 0.20) + (4 * 0.20) + (3 * 0.15) + (4 * 0.15) = 1.20 + 0.80 + 0.80 + 0.45 + 0.60 = **3.85**

### SRC-MXC: Vector Product Search

| Dimension | Score | Evidence |
|-----------|-------|----------|
| DIM-COVERAGE | 3 | Vector similarity search with dual backend (WordPress DB / Pinecone). 0.85 threshold, top 3 results. No WC-specific field enrichment (price/stock not injected). |
| DIM-RELIABILITY | 3 | Works when vector store is populated. 0.85 threshold may filter relevant results. No fallback when vector store empty. Pinecone dependency for external backend. |
| DIM-SECURITY | 3 | Standard DB queries via integrator class. Pinecone API key handled elsewhere. No direct user input injection into queries. |
| DIM-OPERABILITY | 2 | Requires populated vector store. Pinecone requires external service setup. No admin config for WC-specific product search tuning. |
| DIM-EXTENSIBILITY | 2 | Hardcoded thresholds and limits. No hooks or filters for product search customization. Tightly coupled to integrator class. |

**Weighted Total**: (3 * 0.30) + (3 * 0.20) + (3 * 0.20) + (2 * 0.15) + (2 * 0.15) = 0.90 + 0.60 + 0.60 + 0.30 + 0.30 = **2.70**

### SRC-BAA: Multi-Stage Keyword Search + AI Context Formatting

| Dimension | Score | Evidence |
|-----------|-------|----------|
| DIM-COVERAGE | 5 | 4-stage search (exact, weighted, attribute, taxonomy). Dynamic term extraction from catalog. Comprehensive product data formatting (price, categories, attributes, stock with quantity, description). AI recommendation instructions. Fallback to popular/sample products. |
| DIM-RELIABILITY | 4 | Transient caching (configurable TTL). Cache invalidation on product/attribute changes. Graceful fallback chain (search -> popular -> sample). Debug logging gated by option. |
| DIM-SECURITY | 4 | All WP API calls (no raw SQL for product search). Product visibility checks (`$wc_product->is_visible()`). Output escaping in card rendering (`esc_url`, `esc_attr`, `esc_html`, `wp_kses_post`). |
| DIM-OPERABILITY | 5 | Zero-config: enabled by default when WC detected. Product count display. Configurable search tuning (max_terms, min_term_length, cache_duration, exclude_terms). Cache management UI. |
| DIM-EXTENSIBILITY | 4 | `baachal_product_card_data` filter for card customization. `baachal_product_cards_html` filter for HTML override. Admin-configurable search parameters. Dynamic term extraction auto-adapts to catalog. |

**Weighted Total**: (5 * 0.30) + (4 * 0.20) + (4 * 0.20) + (5 * 0.15) + (4 * 0.15) = 1.50 + 0.80 + 0.80 + 0.75 + 0.60 = **4.45**

---

## Capability 2: Catalog Operations

Direct WooCommerce catalog query and manipulation.

### SRC-AXI: Product Indexing Configuration UI

| Dimension | Score | Evidence |
|-----------|-------|----------|
| DIM-COVERAGE | 3 | Admin UI for product selection (None/All/Recent/Search). WC-specific indexing options. But catalog operations are limited to indexing -- no direct product queries outside RAG. |
| DIM-RELIABILITY | 4 | WooCommerce detection before showing options. Conditional UI based on WC availability. Indexing uses standard WP/WC APIs. |
| DIM-SECURITY | 4 | Admin-only UI. Indexing options sanitized. Products accessed via `wc_get_product()` (safe WC API). |
| DIM-OPERABILITY | 3 | Clear UI with selection modes. But requires explicit admin action to index products. No auto-sync for catalog changes. |
| DIM-EXTENSIBILITY | 3 | Configurable indexing options. `aichat_index_text_parts` filter. But no programmatic API for catalog operations. |

**Weighted Total**: (3 * 0.30) + (4 * 0.20) + (4 * 0.20) + (3 * 0.15) + (3 * 0.15) = 0.90 + 0.80 + 0.80 + 0.45 + 0.45 = **3.40**

### SRC-MXC: Product Extraction from Chat Messages

| Dimension | Score | Evidence |
|-----------|-------|----------|
| DIM-COVERAGE | 3 | Fuzzy product matching from natural language. Searches name, slug, SKU. Scoring with consecutive and exact match bonuses. |
| DIM-RELIABILITY | 2 | Loads ALL products on every call (`'limit' => -1`). O(n) performance. No caching. Verbose `error_log()` in production. Fragile with large catalogs (1000+ products). |
| DIM-SECURITY | 3 | `sanitize_text_field()` on input. `intval()` not applicable here. No SQL injection risk (uses WC API). But `error_log()` with user messages is a minor privacy concern. |
| DIM-OPERABILITY | 2 | No admin configuration. Hardcoded stop words. No tuning for search relevance. Performance degrades with catalog size. |
| DIM-EXTENSIBILITY | 1 | No hooks, filters, or configuration. Hardcoded matching algorithm. Cannot add custom scoring factors or search sources. |

**Weighted Total**: (3 * 0.30) + (2 * 0.20) + (3 * 0.20) + (2 * 0.15) + (1 * 0.15) = 0.90 + 0.40 + 0.60 + 0.30 + 0.15 = **2.35**

### SRC-BAA: Dynamic Product Terms + Admin Configuration

| Dimension | Score | Evidence |
|-----------|-------|----------|
| DIM-COVERAGE | 4 | Dynamic term extraction from catalog (categories, attributes, tags, title phrases). Admin search configuration with 6 settings. Cache management. WooCommerce detection with product count. |
| DIM-RELIABILITY | 4 | Transient-cached term extraction. Cache invalidation on WC attribute changes. Configurable parameters prevent edge cases. |
| DIM-SECURITY | 4 | Standard WP/WC APIs. Admin-only settings. Sanitized option values. |
| DIM-OPERABILITY | 5 | Full admin UI with cache management. Configurable max_terms, min_term_length, cache_duration, exclude_terms. Documentation in search tab. |
| DIM-EXTENSIBILITY | 3 | Admin-configurable but no programmatic hooks for term extraction customization. |

**Weighted Total**: (4 * 0.30) + (4 * 0.20) + (4 * 0.20) + (5 * 0.15) + (3 * 0.15) = 1.20 + 0.80 + 0.80 + 0.75 + 0.45 = **4.00**

---

## Capability 3: Cart and Order Handling

Direct WooCommerce cart and order operations from chat.

### SRC-AXI: N/A

| Dimension | Score | Evidence |
|-----------|-------|----------|
| DIM-COVERAGE | 0 | No cart manipulation or order access. |
| DIM-RELIABILITY | 0 | N/A -- feature not present. |
| DIM-SECURITY | 0 | N/A -- feature not present. |
| DIM-OPERABILITY | 0 | N/A -- feature not present. |
| DIM-EXTENSIBILITY | 0 | N/A -- feature not present. |

**Weighted Total**: **0.00**

### SRC-MXC: Add to Cart + Order History + Cart Check

| Dimension | Score | Evidence |
|-----------|-------|----------|
| DIM-COVERAGE | 4 | Add to cart (AJAX), order history (last 1 or 5), cart status check. Missing: checkout redirect (PRO-only stub), variable product/variation support, quantity selection. |
| DIM-RELIABILITY | 3 | WC API calls are standard. Nonce validation. User binding for orders. But no product existence validation before `add_to_cart()`. No error recovery for cart session issues. |
| DIM-SECURITY | 3 | Nonce validation present. `intval()` sanitization on product_id. User binding via `get_current_user_id()` + WC session fallback. But no capability check for cart operations. No product visibility validation. |
| DIM-OPERABILITY | 3 | Works via AJAX hooks. Registered for both logged-in and guest users. Order details include license_key metadata. |
| DIM-EXTENSIBILITY | 2 | No hooks or filters on cart/order operations. Hardcoded response messages. No event emission for tracking. |

**Weighted Total**: (4 * 0.30) + (3 * 0.20) + (3 * 0.20) + (3 * 0.15) + (2 * 0.15) = 1.20 + 0.60 + 0.60 + 0.45 + 0.30 = **3.15**

### SRC-BAA: N/A

| Dimension | Score | Evidence |
|-----------|-------|----------|
| DIM-COVERAGE | 0 | No cart or order capabilities. |
| DIM-RELIABILITY | 0 | N/A -- feature not present. |
| DIM-SECURITY | 0 | N/A -- feature not present. |
| DIM-OPERABILITY | 0 | N/A -- feature not present. |
| DIM-EXTENSIBILITY | 0 | N/A -- feature not present. |

**Weighted Total**: **0.00**

---

## Capability 4: Product Presentation and Recommendations

Visual product presentation in chat interface.

### SRC-AXI: N/A

| Dimension | Score | Evidence |
|-----------|-------|----------|
| DIM-COVERAGE | 0 | No product card rendering. Products appear as text in RAG context. |
| DIM-RELIABILITY | 0 | N/A -- feature not present. |
| DIM-SECURITY | 0 | N/A -- feature not present. |
| DIM-OPERABILITY | 0 | N/A -- feature not present. |
| DIM-EXTENSIBILITY | 0 | N/A -- feature not present. |

**Weighted Total**: **0.00**

### SRC-MXC: Product Card HTML + Context Tracking

| Dimension | Score | Evidence |
|-----------|-------|----------|
| DIM-COVERAGE | 3 | Product card HTML in responses (`productCardHtml` property). Context tracking for follow-up questions (transient-based). Card rendering delegated to PRO add-on. |
| DIM-RELIABILITY | 3 | Per-request HTML reset. Separate bot message for cards. But context tracking uses global transient (race condition across users). |
| DIM-SECURITY | 3 | Card HTML generated server-side. But actual card rendering is in PRO add-on (not auditable in base plugin). |
| DIM-OPERABILITY | 2 | Requires PRO add-on for full card rendering. Basic plugin provides hook infrastructure only. |
| DIM-EXTENSIBILITY | 2 | `html` key in JSON response allows frontend customization. But no filters on card generation. Context tracking not extensible. |

**Weighted Total**: (3 * 0.30) + (3 * 0.20) + (3 * 0.20) + (2 * 0.15) + (2 * 0.15) = 0.90 + 0.60 + 0.60 + 0.30 + 0.30 = **2.70**

### SRC-BAA: Product Card Grid with Full Data

| Dimension | Score | Evidence |
|-----------|-------|----------|
| DIM-COVERAGE | 5 | Full product card grid: image (with placeholder), title, price (HTML/text fallback), link (target="_blank"). AI recommendation instructions guide response quality. Cards auto-appended to bot replies. |
| DIM-RELIABILITY | 4 | Product visibility checks. Price fallback chain. Image placeholder when missing. Handles all stock statuses. |
| DIM-SECURITY | 5 | Comprehensive output escaping: `esc_url()`, `esc_attr()`, `esc_html()`, `wp_kses_post()`. `rel="noopener"` on links. Alt text on images. |
| DIM-OPERABILITY | 4 | Zero-config: cards auto-render when products found. No additional setup required. |
| DIM-EXTENSIBILITY | 4 | `baachal_product_card_data` filter pre-render. `baachal_product_cards_html` filter post-render. Easy to add custom card elements. |

**Weighted Total**: (5 * 0.30) + (4 * 0.20) + (5 * 0.20) + (4 * 0.15) + (4 * 0.15) = 1.50 + 0.80 + 1.00 + 0.60 + 0.60 = **4.50**

---

## Capability 5: WooCommerce Platform Compatibility

Technical compatibility with WooCommerce platform features.

### SRC-AXI: N/A

| Dimension | Score | Evidence |
|-----------|-------|----------|
| DIM-COVERAGE | 0 | No HPOS declaration. No cache invalidation for WC changes. |
| DIM-RELIABILITY | 0 | N/A -- feature not present. |
| DIM-SECURITY | 0 | N/A -- feature not present. |
| DIM-OPERABILITY | 0 | N/A -- feature not present. |
| DIM-EXTENSIBILITY | 0 | N/A -- feature not present. |

**Weighted Total**: **0.00**

### SRC-MXC: N/A

| Dimension | Score | Evidence |
|-----------|-------|----------|
| DIM-COVERAGE | 0 | No HPOS declaration. Uses WC CRUD API (HPOS-safe) but no formal declaration. No cache invalidation. |
| DIM-RELIABILITY | 0 | N/A -- feature not present. |
| DIM-SECURITY | 0 | N/A -- feature not present. |
| DIM-OPERABILITY | 0 | N/A -- feature not present. |
| DIM-EXTENSIBILITY | 0 | N/A -- feature not present. |

**Weighted Total**: **0.00**

### SRC-BAA: HPOS + Cache Invalidation

| Dimension | Score | Evidence |
|-----------|-------|----------|
| DIM-COVERAGE | 4 | HPOS compatibility declared. Cache invalidation on post lifecycle + WC attribute changes. Covers product and attribute mutations. |
| DIM-RELIABILITY | 5 | Proper `before_woocommerce_init` timing for HPOS. Multi-hook cache invalidation covers all mutation paths. |
| DIM-SECURITY | 4 | Standard WP/WC hooks. No elevated permissions required. |
| DIM-OPERABILITY | 5 | Zero-config: automatic. No admin setup needed. |
| DIM-EXTENSIBILITY | 3 | Standard WP hooks -- can be unhooked if needed. No custom extension points. |

**Weighted Total**: (4 * 0.30) + (5 * 0.20) + (4 * 0.20) + (5 * 0.15) + (3 * 0.15) = 1.20 + 1.00 + 0.80 + 0.75 + 0.45 = **4.20**

---

## Aggregate Scoring Summary

### Per-Capability Scores

| Capability | SRC-AXI | SRC-MXC | SRC-BAA | Winner |
|-----------|---------|---------|---------|--------|
| Product Context Injection | 3.85 | 2.70 | **4.45** | SRC-BAA |
| Catalog Operations | 3.40 | 2.35 | **4.00** | SRC-BAA |
| Cart and Order Handling | 0.00 (N/A) | **3.15** | 0.00 (N/A) | SRC-MXC |
| Product Presentation | 0.00 (N/A) | 2.70 | **4.50** | SRC-BAA |
| WC Platform Compatibility | 0.00 (N/A) | 0.00 (N/A) | **4.20** | SRC-BAA |

### Aggregate Scores (All 5 Capabilities)

| Source | Raw Average (all 5) | Adjusted Average (excl. N/A caps) | Capabilities Won |
|--------|---------------------|-----------------------------------|-----------------|
| SRC-AXI | (3.85 + 3.40 + 0.00 + 0.00 + 0.00) / 5 = **1.45** | (3.85 + 3.40) / 2 = **3.63** | 0 |
| SRC-MXC | (2.70 + 2.35 + 3.15 + 2.70 + 0.00) / 5 = **2.18** | (2.70 + 2.35 + 3.15 + 2.70) / 4 = **2.73** | 1 |
| SRC-BAA | (4.45 + 4.00 + 0.00 + 4.50 + 4.20) / 5 = **3.43** | (4.45 + 4.00 + 4.50 + 4.20) / 4 = **4.29** | 4 |

### N/A Impact Note

SRC-AXI's raw aggregate (1.45) is severely depressed by 3 N/A capabilities (cart/order, presentation, platform compatibility). Its present capabilities average 3.63 (investigate-level). This mirrors the SRC-AXI runtime scoring in session 02 where streaming N/A depressed the aggregate.

SRC-MXC has 1 N/A (platform compatibility) and its present capabilities average 2.73 (investigate-level).

SRC-BAA has 1 N/A (cart/order) but leads in 4 of 5 capabilities with adjusted average 4.29 (adopt-level).

### Band Assignment

| Source | Adjusted Average | Band | Rationale |
|--------|-----------------|------|-----------|
| SRC-AXI | 3.63 | investigate | No dimension below 3 in present capabilities. But 3 N/A capabilities limit WooCommerce coverage. |
| SRC-MXC | 2.73 | investigate | DIM-EXTENSIBILITY = 1 in catalog operations. Cart/order is sole exclusive capability (3.15). |
| SRC-BAA | 4.29 | **adopt** | >= 3.8 threshold met. No dimension below 3 in any scored capability. DIM-SECURITY >= 3 on all. Leads 4 of 5 capabilities. |

---

## Arithmetic Verification

### Product Context Injection
- SRC-AXI: (4*0.30)+(4*0.20)+(4*0.20)+(3*0.15)+(4*0.15) = 1.20+0.80+0.80+0.45+0.60 = 3.85 CHECK
- SRC-MXC: (3*0.30)+(3*0.20)+(3*0.20)+(2*0.15)+(2*0.15) = 0.90+0.60+0.60+0.30+0.30 = 2.70 CHECK
- SRC-BAA: (5*0.30)+(4*0.20)+(4*0.20)+(5*0.15)+(4*0.15) = 1.50+0.80+0.80+0.75+0.60 = 4.45 CHECK

### Catalog Operations
- SRC-AXI: (3*0.30)+(4*0.20)+(4*0.20)+(3*0.15)+(3*0.15) = 0.90+0.80+0.80+0.45+0.45 = 3.40 CHECK
- SRC-MXC: (3*0.30)+(2*0.20)+(3*0.20)+(2*0.15)+(1*0.15) = 0.90+0.40+0.60+0.30+0.15 = 2.35 CHECK
- SRC-BAA: (4*0.30)+(4*0.20)+(4*0.20)+(5*0.15)+(3*0.15) = 1.20+0.80+0.80+0.75+0.45 = 4.00 CHECK

### Cart and Order Handling
- SRC-MXC: (4*0.30)+(3*0.20)+(3*0.20)+(3*0.15)+(2*0.15) = 1.20+0.60+0.60+0.45+0.30 = 3.15 CHECK

### Product Presentation
- SRC-MXC: (3*0.30)+(3*0.20)+(3*0.20)+(2*0.15)+(2*0.15) = 0.90+0.60+0.60+0.30+0.30 = 2.70 CHECK
- SRC-BAA: (5*0.30)+(4*0.20)+(5*0.20)+(4*0.15)+(4*0.15) = 1.50+0.80+1.00+0.60+0.60 = 4.50 CHECK

### WC Platform Compatibility
- SRC-BAA: (4*0.30)+(5*0.20)+(4*0.20)+(5*0.15)+(3*0.15) = 1.20+1.00+0.80+0.75+0.45 = 4.20 CHECK

All arithmetic verified. No discrepancies.
