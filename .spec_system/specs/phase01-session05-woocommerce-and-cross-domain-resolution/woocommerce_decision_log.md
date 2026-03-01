# WooCommerce Decision Log

**Log ID**: `DL-P01-S05-WOO`
**Domain**: DOM-WOOCOMMERCE
**Session**: phase01-session05-woocommerce-and-cross-domain-resolution
**Created**: 2026-03-01

---

## Decision Summary

| Decision ID | Capability | Winner | Score | Outcome | Risk |
|-------------|-----------|--------|-------|---------|------|
| DEC-WOO-001 | Product Context Injection | SRC-BAA | 4.45 | adopt_with_modifications | low |
| DEC-WOO-002 | Catalog Operations | SRC-BAA | 4.00 | adopt_with_modifications | medium |
| DEC-WOO-003 | Cart and Order Handling | SRC-MXC | 3.15 | adopt_with_modifications | medium |
| DEC-WOO-004 | Product Presentation | SRC-BAA | 4.50 | adopt_with_modifications | low |
| DEC-WOO-005 | WC Platform Compatibility | SRC-BAA | 4.20 | adopt_as_is | low |

---

## DEC-WOO-001: Product Context Injection

**Winner**: `SRC-BAA` (4.45)
**Runner-up**: SRC-AXI (3.85), SRC-MXC (2.70)
**Outcome**: `adopt_with_modifications`

### Rationale

Baachal provides the most comprehensive product context injection pipeline: 4-stage keyword search with dynamic catalog term extraction, comprehensive product data formatting (price, categories, attributes, stock with quantity, description), and structured AI recommendation instructions. Zero-config operation (enabled by default when WC detected) and configurable search tuning (max_terms, cache_duration, exclude_terms) provide superior operability.

SRC-AXI's approach is architecturally elegant (RAG indexing + query-time enrichment) and provides the best data freshness guarantee (live price/stock at query time), but requires RAG pipeline setup and has no standalone WooCommerce product search outside the RAG context.

SRC-MXC's vector search provides semantic matching capability but lacks WC-specific field enrichment (no price/stock injection) and has limited configuration options.

### Modifications Required

1. **Integrate SRC-AXI query-time enrichment pattern**: Apply the `aichat_enrich_context_with_product_data()` concept to ensure price and stock data are always fresh at query time rather than relying solely on cached search results.
2. **Add vector search as complementary retrieval mode**: When RAG infrastructure is available (Phase 02+), offer vector similarity as an additional search stage alongside keyword search, combining SRC-BAA's keyword stages with SRC-AXI/MXC's vector capabilities.
3. **Align with RAG domain**: Product context injection must integrate with the RAG context assembly pipeline (DEC-RAG-004) rather than operating as a parallel system. SRC-BAA's standalone approach should feed into the unified context assembly.

### Carry-Forward

`CFI-WOO-001` (medium, Phase 02): Design hybrid product retrieval combining BAA keyword search (zero-dependency baseline) with AXI/MXC vector search (when RAG infrastructure available). Ensure query-time data enrichment for price/stock freshness.

### Affected Domains

- **DOM-RAG**: Product context injection overlaps with RAG content retrieval. Must define boundary: RAG indexes products; WooCommerce domain handles WC-specific data enrichment and search.
- **DOM-RUNTIME**: Product context injected into system prompt at runtime message lifecycle.

### Source Attribution

| Pattern | Source | Reference |
|---------|--------|-----------|
| Multi-stage keyword search | SRC-BAA | `baachal/baachal.php:1476-1568` |
| Dynamic term extraction | SRC-BAA | `baachal/baachal.php:1570-1628` |
| AI context formatting | SRC-BAA | `baachal/baachal.php:2033-2169` |
| Query-time enrichment | SRC-AXI | `axiachat-ai/includes/contexto-functions.php:520-567` |
| Vector product search | SRC-MXC | `mxchat-basic/includes/class-mxchat-integrator.php:5923-6120` |

---

## DEC-WOO-002: Catalog Operations

**Winner**: `SRC-BAA` (4.00)
**Runner-up**: SRC-AXI (3.40), SRC-MXC (2.35)
**Outcome**: `adopt_with_modifications`

### Rationale

Baachal leads catalog operations with dynamic product term extraction from the store catalog (categories, attributes, tags, title phrases), admin-configurable search parameters, and cache management. The auto-adapting vocabulary ensures search relevance improves with catalog richness.

SRC-AXI provides admin indexing UI with product selection modes but catalog operations are limited to indexing actions rather than direct queries.

SRC-MXC's product extraction from messages has the interesting capability of parsing product references from natural language, but the implementation has critical scalability issues (loads ALL products per call, no caching) and zero extensibility (DIM-EXTENSIBILITY=1).

### Modifications Required

1. **Integrate SRC-MXC message-to-product matching concept**: The ability to extract product references from user messages is valuable for conversational commerce. Reimplement with caching, catalog-size limits, and configurable matching parameters.
2. **Add SRC-AXI indexing UI patterns**: The product selection modes (None/All/Recent/Search) from SRC-AXI's training UI provide admin control over which products are available to the chatbot.

### Carry-Forward

`CFI-WOO-002` (medium, Phase 02): Reimplement product-from-message extraction with scalable matching (cached product index, configurable thresholds). Integrate SRC-AXI product selection modes for admin control over chatbot product scope.

### Affected Domains

- **DOM-ADMIN**: Catalog configuration UI is an admin page concern.
- **DOM-RAG**: Product indexing configuration overlaps with RAG ingestion settings.

### Source Attribution

| Pattern | Source | Reference |
|---------|--------|-----------|
| Dynamic term extraction | SRC-BAA | `baachal/baachal.php:1570-1628` |
| Search configuration UI | SRC-BAA | `baachal/admin/tabs/search.php` |
| Product selection modes | SRC-AXI | `axiachat-ai/includes/training-context.php:222-263` |
| Message-to-product matching | SRC-MXC | `mxchat-basic/includes/class-mxchat-woocommerce.php:112-234` |

---

## DEC-WOO-003: Cart and Order Handling

**Winner**: `SRC-MXC` (3.15)
**Runner-up**: SRC-AXI (N/A), SRC-BAA (N/A)
**Outcome**: `adopt_with_modifications`

### Rationale

MxChat is the only source with direct WooCommerce cart and order manipulation. The add-to-cart AJAX endpoint, order history retrieval, and cart status check provide the foundation for conversational commerce interactions. The user binding for order history (current user + WC session fallback) is secure.

Neither SRC-AXI nor SRC-BAA implement cart or order handling -- their WooCommerce integrations are read-only (product context for AI responses).

While SRC-MXC's implementation has gaps (no product visibility validation, no variable product support, no quantity selection, global product context tracking), it is the only starting point for these capabilities.

### Modifications Required

1. **Add product validation**: Verify product exists, is published, and is purchasable before `add_to_cart()`. Handle variable products requiring variation selection.
2. **Add quantity support**: Accept quantity parameter in add-to-cart endpoint.
3. **Add capability check**: Consider whether cart operations should require user authentication or remain open to guests.
4. **Fix context tracking**: Replace global transient (`mxchat_last_product`) with per-session or per-user tracking to prevent cross-user contamination.
5. **Add checkout redirect**: Implement the checkout redirect callback (defined but not implemented in base plugin).
6. **Remove production logging**: Replace `error_log()` calls with structured logger (if available) or remove entirely.

### Carry-Forward

`CFI-WOO-003` (high, Phase 02): Implement validated cart operations (product existence/visibility/purchasability checks, variable product support, quantity), per-session product context tracking, and checkout redirect. Remove production `error_log()` calls.

### Affected Domains

- **DOM-RUNTIME**: Cart operations triggered from chat message lifecycle.
- **DOM-SECURITY**: Cart endpoint security (nonce, capability checks, product validation).

### Source Attribution

| Pattern | Source | Reference |
|---------|--------|-----------|
| Add to cart AJAX | SRC-MXC | `mxchat-basic/includes/class-mxchat-woocommerce.php:20-43` |
| Order history | SRC-MXC | `mxchat-basic/includes/class-mxchat-woocommerce.php:46-97` |
| Cart status check | SRC-MXC | `mxchat-basic/includes/class-mxchat-woocommerce.php:101-104` |
| Product context tracking | SRC-MXC | `mxchat-basic/includes/class-mxchat-woocommerce.php:239-248` |

---

## DEC-WOO-004: Product Presentation and Recommendations

**Winner**: `SRC-BAA` (4.50) -- **highest score in WooCommerce domain**
**Runner-up**: SRC-MXC (2.70), SRC-AXI (N/A)
**Outcome**: `adopt_with_modifications`

### Rationale

Baachal's product card grid provides the most complete visual product presentation: semantic HTML (`<article>` cards), image with placeholder fallback, title, price (HTML/text fallback chain), and link with `rel="noopener"`. Output escaping is comprehensive (DIM-SECURITY=5) and the implementation is extensible via two filters (`baachal_product_card_data`, `baachal_product_cards_html`). AI recommendation instructions ensure quality responses.

SRC-MXC provides product card infrastructure but the actual card rendering is in the PRO add-on (not auditable in base plugin). Product context tracking via global transient has a race condition.

SRC-AXI has no product presentation -- products appear as plain text in RAG context.

### Modifications Required

1. **Integrate SRC-MXC product context tracking concept**: Per-session product tracking for contextual follow-up questions, but with per-session isolation (not global transient).
2. **Add product card interactivity**: Consider "Add to Cart" buttons on product cards (bridging DEC-WOO-003 cart operations with DEC-WOO-004 presentation).

### Carry-Forward

`CFI-WOO-004` (medium, Phase 02): Enhance product card grid with interactive elements (add-to-cart buttons, quick-view). Add per-session product context tracking for conversational follow-up.

### Affected Domains

- **DOM-RUNTIME**: Product cards appended to bot responses in runtime pipeline.
- **DOM-ADMIN**: Product card styling/configuration could be admin-configurable.

### Source Attribution

| Pattern | Source | Reference |
|---------|--------|-----------|
| Product card grid HTML | SRC-BAA | `baachal/baachal.php:2172-2227` |
| Product data formatting | SRC-BAA | `baachal/baachal.php:2033-2169` |
| AI recommendation instructions | SRC-BAA | `baachal/baachal.php:2162-2168` |
| Card filter hooks | SRC-BAA | `baachal_product_card_data`, `baachal_product_cards_html` |
| Product context tracking concept | SRC-MXC | `mxchat-basic/includes/class-mxchat-woocommerce.php:239-248` |

---

## DEC-WOO-005: WooCommerce Platform Compatibility

**Winner**: `SRC-BAA` (4.20)
**Runner-up**: SRC-AXI (N/A), SRC-MXC (N/A)
**Outcome**: `adopt_as_is`

### Rationale

Baachal is the only source with formal WooCommerce platform compatibility: HPOS (High-Performance Order Storage) declaration via `FeaturesUtil::declare_compatibility()` and cache invalidation on product/attribute changes. The HPOS declaration is required for WC 9.0+ compatibility and is a zero-effort adopt.

SRC-MXC uses WC CRUD API (which is HPOS-safe) but has no formal declaration. SRC-AXI has no HPOS-relevant code.

Cache invalidation ensures product search results stay fresh when products are created, updated, deleted, or when attributes change.

### Carry-Forward

None -- adopt as-is.

### Affected Domains

- None -- platform compatibility is self-contained.

### Source Attribution

| Pattern | Source | Reference |
|---------|--------|-----------|
| HPOS declaration | SRC-BAA | `baachal/baachal.php:38-43` |
| Cache invalidation hooks | SRC-BAA | `baachal/baachal.php:89-126` |

---

## Domain Summary

| Metric | Value |
|--------|-------|
| Total Capabilities | 5 |
| Adopt-As-Is | 1 |
| Adopt-With-Modifications | 4 |
| Domain Winner | **SRC-BAA** (4 of 5 capabilities) |
| Secondary Winner | SRC-MXC (1 of 5: cart/order handling) |
| Aggregate Scores (adjusted, excl. N/A) | SRC-BAA: 4.29, SRC-AXI: 3.63, SRC-MXC: 2.73 |
| Band | SRC-BAA: adopt, SRC-AXI: investigate, SRC-MXC: investigate |
| Carry-Forward Items | CFI-WOO-001 through CFI-WOO-004 |
| Key Risks | RAG-WooCommerce boundary definition; cart operation security hardening |

### Cross-Domain Interaction Notes

- **DOM-WOOCOMMERCE x DOM-RAG**: Product context injection overlaps with RAG content retrieval. SRC-AXI's approach treats products as RAG content; SRC-BAA treats products as a standalone search. The unified system must define boundary: RAG handles generic content indexing/retrieval; WooCommerce domain handles WC-specific product data enrichment, product search, and commerce operations.
- **DOM-WOOCOMMERCE x DOM-RUNTIME**: Product context injected into system prompt. Cart operations triggered from chat. Product cards appended to bot responses.
- **DOM-WOOCOMMERCE x DOM-ADMIN**: WooCommerce admin settings (enable toggle, search config). Catalog configuration UI.
- **DOM-WOOCOMMERCE x DOM-SECURITY**: Cart endpoint security (nonce, product validation). Product data escaping. API key handling for WC REST API (SRC-MXC consumer key/secret).
- **DOM-WOOCOMMERCE x DOM-TOOLS**: Product operations (add to cart, order history) could be exposed as AI tools via tool registration API (DEC-TOL-001).
