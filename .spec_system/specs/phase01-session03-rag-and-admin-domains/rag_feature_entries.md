# RAG Feature Entries

**Session ID**: `phase01-session03-rag-and-admin-domains`
**Domain**: `DOM-RAG`
**Context Pack**: `CP-P01-S03-001`
**Created**: 2026-03-01

---

## SRC-AXI Feature Entries

### FEAT-AXI-RAG-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-RAG-001` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-RAG-AXI-001` |
| `source_path` | `axiachat-ai/includes/contexto-functions.php` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Multi-source content ingestion with page builder support` |
| `capability_summary` | Ingests WordPress posts, pages, products (WooCommerce), uploaded files, and web-scraped content. Content cleaning pipeline handles shortcodes (`do_shortcode`), Elementor JSON postmeta (15+ widget types via recursive walker), HTML stripping, and whitespace normalization. Configurable per-context indexing options: excerpt, URL, featured image, taxonomies, custom meta keys, WooCommerce fields. Dynamic WC data (price, stock, SKU) separated for query-time enrichment. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-ADMIN"]` |
| `evidence_refs` | `["docs/axiachat-ai-feature-map.md"]` |

---

### FEAT-AXI-RAG-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-RAG-002` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-RAG-AXI-001` |
| `source_path` | `axiachat-ai/includes/contexto-functions.php` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Overlap-based text chunking with embedding indexing` |
| `capability_summary` | Chunks text via `aichat_split_text_into_chunks()` with default 1000-word target and 180-word overlap. Full indexing pipeline per post: build index text, chunk, generate embedding per chunk, delete-then-insert to `aichat_chunks` DB table. Stores post_id, context_id, chunk_index, type, title, content, embedding (JSON), tokens, timestamps. Per-context configurable indexing options stored as JSON. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `external_ai_provider_required` |
| `risk_level` | `medium` |

---

### FEAT-AXI-RAG-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-RAG-003` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-RAG-AXI-001` |
| `source_path` | `axiachat-ai/includes/contexto-functions.php` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Dual-mode retrieval with vector similarity and Pinecone` |
| `capability_summary` | Supports 4 retrieval modes: local (brute-force PHP cosine similarity over all chunks per context), Pinecone (remote vector query with namespace `aichat_context_{id}`), page (current page content with score 1.0), and auto (determines local vs Pinecone from context config). All modes enrich product results with live WooCommerce data at query time. Pinecone endpoint sanitized with HTTPS-only and domain allowlist. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `optional_external_service` |
| `risk_level` | `medium` |

---

### FEAT-AXI-RAG-004

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-RAG-004` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-RAG-AXI-001` |
| `source_path` | `axiachat-ai/includes/contexto-functions.php` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Sophisticated context assembly with security policy and template tokens` |
| `capability_summary` | Builds LLM prompt with system message (security policy + datetime + user context + bot instructions with template token resolution + MCP tools notice) and user message (numbered CONTEXT blocks + QUESTION + LINK marker instructions). Template tokens (`{{site.name}}`, `{{date}}`, etc.) and safe function calls (`{{fn:identifier(args)}}`) with schema validation and output sanitization. Post-answer `[LINK:N]` markers replaced with `<a>` tags to post permalinks or external URLs. Link deduplication, suggestion generation policy (1-6 JSON), context max_length truncation. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-SECURITY"]` |

---

### FEAT-AXI-RAG-005

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-RAG-005` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-RAG-AXI-001` |
| `source_path` | `axiachat-ai/includes/contexto-functions.php` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Multi-provider embedding generation with provider persistence` |
| `capability_summary` | Generates embeddings via OpenAI (`text-embedding-3-small`), Gemini (`gemini-embedding-001` with task type differentiation), or Claude (falls back to OpenAI). Agency proxy mode for centralized key management. Per-context embedding provider auto-detection and DB persistence. External provider override via `aichat_generate_embedding_override` filter. Provider resolution priority: preference > OpenAI > Gemini > Claude > Agency default. Available providers list with key status and default model names. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `external_ai_provider_required` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-PROVIDER"]` |

---

### FEAT-AXI-RAG-006

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-RAG-006` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-RAG-AXI-001` |
| `source_path` | `axiachat-ai/includes/add-ons/web-scraper/scraper-core.php` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Web content extraction with multi-strategy pipeline` |
| `capability_summary` | DOMDocument + XPath-based web scraper with 4-strategy content extraction: (1) Schema.org articleBody, (2) CSS class selectors (30+ patterns covering WordPress, TagDiv, WPBakery, GeneratePress, Astra, OceanWP), (3) HTML5 article fallback, (4) readability heuristic (paragraph scoring). Noise removal with paragraph safety check. Anti-block headers (Chrome UA). Title extraction 3-tier (og:title > h1 > title). Content truncation at 15K chars. Returns structured result or WP_Error. |
| `implementation_status` | `implemented` |
| `maturity_level` | `beta_or_addon` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `medium` |

---

## SRC-MXC Feature Entries

### FEAT-MXC-RAG-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-RAG-001` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-RAG-MXC-001` |
| `source_path` | `mxchat-basic/admin/class-knowledge-manager.php` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Broadest multi-source ingestion with queue processing` |
| `capability_summary` | Ingests from 8+ sources: manual text, single URL (DOM extraction with 20+ XPath selectors), XML sitemap (auto-detect Yoast/Rank Math/WP Core, batch queue), PDF (Smalot parser with repair/fallback), WP posts/pages/products/CPTs, ACF fields (15+ types including repeater/flexible content), WooCommerce metadata (pricing/SKU/categories/custom tabs/variable products), bbPress forum topics. Queue-based processing via `mxchat_processing_queue` DB table with priority ordering, 3 retry attempts, and browser-driven batch processing (5 concurrent). Auto-sync on post update/delete with ACF `save_post` integration. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-ADMIN"]` |

---

### FEAT-MXC-RAG-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-RAG-002` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-RAG-MXC-001` |
| `source_path` | `mxchat-basic/includes/class-mxchat-chunker.php` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Hierarchical text chunking with metadata tagging` |
| `capability_summary` | Dedicated chunker class with 3-tier hierarchical splitting: paragraph boundaries, sentence boundaries, word boundaries. Configurable chunk size (1000-10000 chars, default 4000 ~1000 tokens, admin-settable). No overlap by design -- all chunks reassembled at retrieval time. JSON metadata prefix per chunk (document_type, chunk_index, total_chunks, source_url, parent_url_hash). Deterministic vector IDs (`{md5(url)}_chunk_{index}`). Static factory from WP settings. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |

---

### FEAT-MXC-RAG-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-RAG-003` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-RAG-MXC-001` |
| `source_path` | `mxchat-basic/admin/class-knowledge-manager.php` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Multi-provider embedding generation with bot-specific keys` |
| `capability_summary` | Generates embeddings via OpenAI (`text-embedding-ada-002`, 1536d), Voyage AI (`voyage-3-large` 2048d and variants), or Google Gemini (`gemini-embedding-*`, normalized to 1536d). Bot-specific API key and model selection for multi-bot deployments. Pre-processing: UTF-8 validation, control char removal, token estimation. Storage to WP DB (serialized float arrays) or Pinecone vector DB with metadata. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `external_ai_provider_required` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-PROVIDER"]` |

---

### FEAT-MXC-RAG-004

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-RAG-004` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-RAG-MXC-001` |
| `source_path` | `mxchat-basic/js/knowledge-processing.js` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Browser-driven knowledge processing with real-time monitoring` |
| `capability_summary` | Frontend orchestration for knowledge pipeline (2428 lines). Browser-driven batch queue processing with 5 concurrent items via Promise.all(). Real-time progress bars for PDF pages and sitemap URLs. Sitemap auto-detection UI with expandable index tree. Entry management: grouped chunk display, pagination, search/filter, bulk delete. Entry count live updates via 3-second polling. Error summary tables with per-item failure details. Queue completion verification with stall prevention. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-ADMIN"]` |

---

### FEAT-MXC-RAG-005

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-RAG-005` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-RAG-MXC-001` |
| `source_path` | `mxchat-basic/admin/class-knowledge-manager.php` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Role-based content access restrictions` |
| `capability_summary` | Tag-to-role mappings that restrict knowledge base content by WordPress user role. Hierarchical role restriction: public through administrator. Automatically applies role restrictions to KB entries based on post tag mappings. Bulk role updates for existing content. Multi-bot support with bot-specific Pinecone namespaces. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-SECURITY"]` |

---

## SRC-BAA Feature Entries

### FEAT-BAA-RAG-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-RAG-001` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-RAG-BAA-001` |
| `source_path` | `baachal/includes/content-indexer.php` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Lightweight keyword-based content indexing` |
| `capability_summary` | MySQL FULLTEXT index on custom `baachal_content_index` table. Ingests WP posts/pages only (configurable post types). Auto-index on `save_post` hook. Per-post exclusion via `_baachal_exclude_from_index` meta. Simple content cleaning (strip shortcodes, HTML, normalize whitespace). No chunking, no embeddings, no external API calls. Bulk reindex via TRUNCATE and iterate all published posts. Synchronous indexing only -- no queue, no retry, no batch processing. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |

---

### FEAT-BAA-RAG-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-RAG-002` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-RAG-BAA-001` |
| `source_path` | `baachal/includes/content-indexer.php` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `MySQL FULLTEXT search with LIKE fallback` |
| `capability_summary` | Two-tier retrieval: (1) MySQL FULLTEXT `MATCH...AGAINST` in NATURAL LANGUAGE MODE with BM25-based relevance scoring, (2) fallback to LIKE search with title > excerpt > content priority weighting. Stop word filtering (36 English words). Configurable result limit (default 5, max 20). Returns title, excerpt, URL, post_type, relevance per result. No vector similarity, no embeddings, no external API calls. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |

---

### FEAT-BAA-RAG-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-RAG-003` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-RAG-BAA-001` |
| `source_path` | `baachal/includes/content-indexer.php` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Chunking and embedding parity` |
| `capability_summary` | [N/A] Baachal has no text chunking capability. Entire post content is stored as a single record in the index table. No embedding generation, no vector storage, no external embedding API integration. Keyword-based MySQL FULLTEXT is the only retrieval mechanism. |
| `implementation_status` | `na_no_matching_implementation` |
| `maturity_level` | `na_no_matching_implementation` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |
| `na_reason` | `No chunking, embedding, or vector storage implementation. Content indexer relies entirely on MySQL FULLTEXT keyword search without semantic understanding.` |

---

### FEAT-BAA-RAG-004

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-RAG-004` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-RAG-BAA-001` |
| `source_path` | `baachal/includes/content-indexer.php` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Context assembly and prompt construction parity` |
| `capability_summary` | [N/A] Baachal has no dedicated context assembly or prompt construction system comparable to AxiaChat's `aichat_build_messages()`. Search results are passed to the AI provider in a simpler format without security policy injection, template tokens, LINK markers, or suggestion generation policies. Context formatting is inline in the main plugin file, not a dedicated RAG module. |
| `implementation_status` | `na_no_matching_implementation` |
| `maturity_level` | `na_no_matching_implementation` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |
| `na_reason` | `No dedicated context assembly module. Basic search results passed to provider inline without structured prompt construction, security policies, or attribution markers.` |

---

## Feature Entry Summary

| Source | Feature Count | Implemented | N/A |
|--------|---------------|-------------|-----|
| `SRC-AXI` | 6 | 6 | 0 |
| `SRC-MXC` | 5 | 5 | 0 |
| `SRC-BAA` | 4 | 2 | 2 |
| **Total** | **15** | **13** | **2** |
