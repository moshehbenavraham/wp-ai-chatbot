# RAG Domain Context Pack

## Pack Metadata

| Field | Value |
|---|---|
| `pack_id` | `CP-P01-S03-001` |
| `session_id` | `phase01-session03-rag-and-admin-domains` |
| `phase` | `1` |
| `created_date` | `2026-03-01` |
| `author` | `implement-ai` |
| `context_budget_tokens` | `48000` |
| `domain_scope` | `["DOM-RAG"]` |
| `source_plugins` | `["SRC-AXI", "SRC-MXC", "SRC-BAA"]` |

---

## Domain: `DOM-RAG` - `rag`

**Slice Count**: 10 slices
**Sources Covered**: `SRC-AXI`, `SRC-MXC`, `SRC-BAA`

---

### Slice 1 - AxiaChat Context Retrieval and Embedding Engine

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S03-001-RAG-01` |
| `anchor_id` | `ANC-RAG-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/contexto-functions.php` |
| `symbol_or_scope` | `aichat_get_context_for_question`, `aichat_generate_embedding`, `aichat_cosine_similarity`, `aichat_resolve_embedding_provider` |
| `excerpt_summary` | Core retrieval engine supporting 3 modes: local (PHP-side cosine similarity over all chunks), Pinecone (remote vector query with namespace), and page (current page content). Multi-provider embedding generation: OpenAI (`text-embedding-3-small`), Gemini (`gemini-embedding-001`), Claude (falls back to OpenAI). Agency proxy mode. Per-context embedding provider persistence in DB. External override filter `aichat_generate_embedding_override`. Query-time WooCommerce enrichment (live price/stock/SKU). 1915 lines total. |
| `excerpt_lines` | `L664-L915` (embedding), `L848-L1083` (retrieval) |
| `token_estimate` | `6500` |
| `secondary_domains` | `["DOM-PROVIDER"]` |
| `notes` | Local retrieval is brute-force O(n) cosine similarity over all chunks. No ANN index. Pinecone endpoint sanitizer with HTTPS-only and domain allowlist. |

---

### Slice 2 - AxiaChat Indexing and Content Preparation

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S03-001-RAG-02` |
| `anchor_id` | `ANC-RAG-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/contexto-functions.php` |
| `symbol_or_scope` | `aichat_index_post`, `aichat_build_index_text`, `aichat_clean_post_content`, `aichat_split_text_into_chunks`, `aichat_extract_elementor_text` |
| `excerpt_summary` | Full indexing pipeline per post: build text (title + cleaned content + configurable fields), chunk (default 1000 words, 180 overlap), generate embedding per chunk, delete-then-insert to `aichat_chunks` table. Content cleaning handles shortcodes (`do_shortcode`), Elementor JSON postmeta (15+ widget types), HTML stripping, whitespace normalization. Configurable indexing options: excerpt, URL, featured image, taxonomies, custom meta, WooCommerce fields. Dynamic WC data (price, stock) separated from static index for query-time enrichment. |
| `excerpt_lines` | `L169-L651` |
| `token_estimate` | `5500` |
| `notes` | Elementor walker recursively extracts text from editor, heading, tabs, accordion, price list, testimonial, alert, CTA, icon list widgets. Content cleaning also handles WPBakery/Divi via `do_shortcode`. |

---

### Slice 3 - AxiaChat Context Assembly and Prompt Building

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S03-001-RAG-03` |
| `anchor_id` | `ANC-RAG-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/contexto-functions.php` |
| `symbol_or_scope` | `aichat_build_messages`, `aichat_replace_link_placeholder`, `aichat_resolve_instruction_tokens`, `aichat_pretty_known_links` |
| `excerpt_summary` | Context assembly into LLM prompt: system message (security policy + datetime + user context + bot instructions with token resolution + MCP tools notice) + user message (numbered CONTEXT blocks + QUESTION + LINK marker instructions). Post-answer processing: `[LINK:N]` markers replaced with `<a>` tags to post permalinks or external URLs for web-scraped content. Template tokens in bot instructions: `{{site.name}}`, `{{date}}`, etc. Safe function calls `{{fn:identifier(args)}}` with schema validation and output sanitization. Suggestion generation policy (1-6 JSON suggestions). Context max_length truncation. |
| `excerpt_lines` | `L1094-L1424`, `L1535-L1711` |
| `token_estimate` | `5000` |
| `secondary_domains` | `["DOM-SECURITY"]` |
| `notes` | Security policy always prepended. Instruction tokens use allowlist registry -- no eval. Link deduplication prevents raw URLs adjacent to generated `<a>` tags. |

---

### Slice 4 - AxiaChat Web Scraper Content Extraction

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S03-001-RAG-04` |
| `anchor_id` | `ANC-RAG-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/add-ons/web-scraper/scraper-core.php` |
| `symbol_or_scope` | `aichat_webscraper_extract_content`, `aichat_webscraper_get_text_content` |
| `excerpt_summary` | Robust web content extractor using DOMDocument + XPath. 4-strategy content extraction pipeline: (1) Schema.org `itemprop="articleBody"`, (2) CSS class selectors (30+ patterns: WordPress, TagDiv, WPBakery, GeneratePress, Astra, OceanWP, generic), (3) `<article>` HTML5 fallback, (4) readability heuristic (paragraph count scoring). Noise removal: unconditional strip of script/style/nav/header/footer/aside + 20+ class/ID pattern removal with paragraph safety check (preserves elements with 3+ substantial paragraphs). Anti-block headers (Chrome UA, Sec-Fetch). Title extraction 3-tier: og:title > h1 > title. Content truncation at 15K chars. 416 lines. |
| `excerpt_lines` | `L32-L403` |
| `token_estimate` | `4500` |
| `notes` | SSL verification disabled for broad compatibility. 403 detection with user-facing message. Add-on module (beta_or_addon maturity). |

---

### Slice 5 - AxiaChat Context Admin UI (Create)

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S03-001-RAG-05` |
| `anchor_id` | `ANC-RAG-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/contexto-create.php` |
| `symbol_or_scope` | `aichat_contexto_create_page` |
| `excerpt_summary` | Admin UI for context creation. Source selection for posts, pages, products (WooCommerce), uploaded files -- each with All/Custom mode and Recent/All/Search browse tabs. Context configuration: name, type (local vs. Pinecone), remote API key and endpoint, embedding provider selector. Advanced indexing options: excerpt, URL, featured image, taxonomy slugs, custom meta keys, WooCommerce fields. Progress bar and log panel for batch processing. 559 lines. |
| `excerpt_lines` | `L6-L559` |
| `token_estimate` | `3000` |
| `secondary_domains` | `["DOM-ADMIN"]` |
| `notes` | Pure admin UI rendering. All output escaped. AJAX handlers are in separate files. |

---

### Slice 6 - AxiaChat Context Admin UI (Modify)

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S03-001-RAG-06` |
| `anchor_id` | `ANC-RAG-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/contexto-modify.php` |
| `symbol_or_scope` | `aichat_contexto_modify_page` |
| `excerpt_summary` | Admin UI for modifying existing contexts. Context selector with doc/chunk counts and processing status. Document management: paginated table with filters (search, type, items per page), bulk remove, refresh. Add documents tab: same source panels as Create page. Processing-in-progress warnings. 473 lines. |
| `excerpt_lines` | `L12-L473` |
| `token_estimate` | `2500` |
| `secondary_domains` | `["DOM-ADMIN"]` |
| `notes` | Direct SQL query to `aichat_contexts` table with aggregate subqueries on `aichat_chunks` for stats. |

---

### Slice 7 - MxChat Knowledge Manager (Ingestion and Queue)

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S03-001-RAG-07` |
| `anchor_id` | `ANC-RAG-MXC-001` |
| `source_id` | `SRC-MXC` |
| `source_path` | `mxchat-basic/admin/class-knowledge-manager.php` |
| `symbol_or_scope` | `MxChat_Knowledge_Manager` class: ingestion, queue processing, embedding generation |
| `excerpt_summary` | Monolithic knowledge management class (7286 lines). Ingestion sources: manual text, single URL (DOM extraction with 20+ XPath selectors), XML sitemap (auto-detect Yoast/Rank Math/WP Core, batch queue), PDF (Smalot parser with repair/fallback), WP posts/pages/products/CPTs, ACF fields (15+ types), WooCommerce metadata (pricing/SKU/categories/variable products/custom tabs), bbPress forum topics. Queue-based processing via `mxchat_processing_queue` DB table with priority ordering, 3 retry attempts, and browser-driven batch processing (5 concurrent items). Multi-provider embeddings: OpenAI (`text-embedding-ada-002`), Voyage AI, Google Gemini. Storage to WP DB or Pinecone vector DB. Auto-sync on post update/delete with ACF integration. Role-based content access via tag-to-role mappings. |
| `excerpt_lines` | `L1-L3500` (ingestion/queue), `L3500-L7286` (content management/AJAX) |
| `token_estimate` | `7000` |
| `secondary_domains` | `["DOM-ADMIN"]` |
| `notes` | Content sanitization includes UTF-8 validation, emoji removal, 65K char limit. Stalled processing detection (5-minute timeout). Multi-bot support with bot-specific API keys and Pinecone namespaces. |

---

### Slice 8 - MxChat Chunker

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S03-001-RAG-08` |
| `anchor_id` | `ANC-RAG-MXC-001` |
| `source_id` | `SRC-MXC` |
| `source_path` | `mxchat-basic/includes/class-mxchat-chunker.php` |
| `symbol_or_scope` | `MxChat_Chunker` class |
| `excerpt_summary` | Dedicated chunking class with 3-tier hierarchical splitting: (1) paragraph boundaries, (2) sentence boundaries, (3) word boundaries. Configurable chunk size (1000-10000 chars, default 4000 ~1000 tokens, admin-settable). No overlap -- design reassembles all chunks at retrieval. JSON metadata prefix per chunk: `document_type`, `chunk_index`, `total_chunks`, `source_url`, `parent_url_hash`. Deterministic vector IDs: `{md5(url)}_chunk_{index}`. Static factory `from_settings()`. 342 lines. |
| `excerpt_lines` | `L1-L342` |
| `token_estimate` | `2000` |
| `notes` | Chunk metadata stored inline in content field (JSON + separator + text). No overlap is a design choice -- all chunks for a URL are reassembled before sending to AI. |

---

### Slice 9 - MxChat Knowledge Processing JS

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S03-001-RAG-09` |
| `anchor_id` | `ANC-RAG-MXC-001` |
| `source_id` | `SRC-MXC` |
| `source_path` | `mxchat-basic/js/knowledge-processing.js` |
| `symbol_or_scope` | Queue processing, progress tracking, entry management |
| `excerpt_summary` | Browser-driven queue processing UI (2428 lines). Batch processing: 5 concurrent items via `Promise.all()`, real-time progress bars for PDF pages and sitemap URLs. Sitemap auto-detection UI with expandable index tree. Entry management: grouped chunk display, pagination, search/filter, bulk delete. Entry count live updates via 3-second polling during processing. Error summary tables with per-item failure details. Queue completion verification with stall prevention. |
| `excerpt_lines` | `L1-L2428` |
| `token_estimate` | `4000` |
| `secondary_domains` | `["DOM-ADMIN"]` |
| `notes` | jQuery-based. localStorage flag triggers queue start after redirect. All AJAX calls include nonce. |

---

### Slice 10 - Baachal Content Indexer

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S03-001-RAG-10` |
| `anchor_id` | `ANC-RAG-BAA-001` |
| `source_id` | `SRC-BAA` |
| `source_path` | `baachal/includes/content-indexer.php`, `baachal/admin/tabs/content.php`, `baachal/admin/tabs/search.php` |
| `symbol_or_scope` | `Baachal_Content_Indexer` class, content admin tab, search admin tab |
| `excerpt_summary` | Lightweight keyword-based content indexer (323 lines main, 129+93 admin tabs). MySQL FULLTEXT index on custom table (`baachal_content_index`). Ingestion: WP posts/pages only (configurable post types), auto-index on `save_post`, per-post exclusion via meta. No chunking, no embeddings, no vector DB. Retrieval: FULLTEXT `MATCH...AGAINST` in NATURAL LANGUAGE MODE with LIKE fallback (title > excerpt > content priority). Stop word filtering (36 English words). Bulk reindex via TRUNCATE + iterate. Admin UI: indexing stats, post type checkboxes, max results (1-20), test search, WooCommerce product search config (term extraction, caching, exclude terms). |
| `excerpt_lines` | Content indexer `L1-L323`, content tab `L1-L129`, search tab `L1-L93` |
| `token_estimate` | `3000` |
| `secondary_domains` | `["DOM-ADMIN"]` |
| `notes` | No embedding, no chunking, no queue, no retry, no external API calls. Keyword-based only. Missing capability checks in indexer class (relies on caller). Product search is WooCommerce keyword extraction with transient caching, not vector-based. |

---

## Pack Summary

| Field | Value |
|---|---|
| `total_slices` | `10` |
| `total_token_estimate` | `43000` |

### Domain Coverage Matrix

| Domain | SRC-AXI | SRC-MXC | SRC-BAA |
|---|---|---|---|
| `DOM-RAG` | `CP-P01-S03-001-RAG-01` through `RAG-06` | `CP-P01-S03-001-RAG-07` through `RAG-09` | `CP-P01-S03-001-RAG-10` |

### Gaps and Risks

- **Retrieval architecture asymmetry**: SRC-AXI uses brute-force PHP cosine similarity (O(n) over all chunks) or Pinecone; SRC-MXC uses Pinecone or WP DB with serialized embeddings; SRC-BAA uses MySQL FULLTEXT (keyword-based, no vectors). Scoring must assess retrieval quality, not just presence.
- **Ingestion breadth variance**: SRC-MXC has the broadest ingestion surface (manual text, URL, sitemap, PDF, WP content, ACF, WooCommerce, bbPress); SRC-AXI covers WP content + web scraper + Elementor; SRC-BAA covers only WP posts/pages. Coverage scoring favors SRC-MXC.
- **Chunking quality**: SRC-AXI has overlap-based chunking (180 words overlap); SRC-MXC has no-overlap paragraph-boundary chunking with metadata and reassembly at retrieval; SRC-BAA has no chunking. Different strategies have different tradeoffs.
- **Embedding provider support**: SRC-AXI supports OpenAI + Gemini + agency proxy; SRC-MXC supports OpenAI + Voyage AI + Gemini; SRC-BAA has no embedding. Provider count alone is less important than provider architecture.
- **Queue processing**: SRC-MXC has DB-backed queue with retry, batch processing, stall detection; SRC-AXI has AJAX-driven batch but no formal queue table; SRC-BAA has synchronous-only indexing. Reliability scoring should account for this.
- **Context assembly sophistication**: SRC-AXI has the most sophisticated prompt construction (security policy, template tokens, safe function calls, MCP awareness, suggestion generation). SRC-MXC and SRC-BAA have simpler context formatting. This affects operability.
