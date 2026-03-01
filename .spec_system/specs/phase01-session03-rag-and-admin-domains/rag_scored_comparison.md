# RAG Scored Comparison

**Session ID**: `phase01-session03-rag-and-admin-domains`
**Domain**: `DOM-RAG`
**Context Pack**: `CP-P01-S03-001`
**Scoring Rubric**: `specs/phase00-session02-domain-taxonomy-feature-schema/scoring_rubric.md`
**Created**: 2026-03-01

---

## Scoring Scale Reference

| Score | Meaning |
|---|---|
| 0 | Not present or unusable |
| 1 | Bare-minimum with major limits |
| 2 | Partial with clear constraints |
| 3 | Solid for common use cases |
| 4 | Strong with low friction |
| 5 | Best-in-class among reference plugins |

**Weighted Total**: `(coverage * 0.30) + (reliability * 0.20) + (security * 0.20) + (operability * 0.15) + (extensibility * 0.15)`

---

## Capability 1: Ingestion Pipeline

Comparison of content ingestion breadth, source diversity, and processing reliability.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-RAG-001` | `SRC-AXI` | 4 | 4 | 4 | 4 | 4 | 4.00 | `adopt` |
| `FEAT-MXC-RAG-001` | `SRC-MXC` | 5 | 4 | 4 | 4 | 3 | 4.15 | `adopt` |
| `FEAT-BAA-RAG-001` | `SRC-BAA` | 2 | 3 | 3 | 3 | 2 | 2.55 | `reject` |

### Evidence Notes

#### FEAT-AXI-RAG-001 - SRC-AXI

- Coverage: 4 - Posts, pages, products, uploaded files, web-scraped content, Elementor/shortcode-rendered content. Misses: sitemap batch, PDF, ACF, bbPress. Configurable indexing options per context.
- Reliability: 4 - Elementor fallback pipeline handles 15+ widget types. Shortcode expansion for page builders. Content cleaning normalizes whitespace. Delete-then-insert ensures clean reindex. No formal queue/retry.
- Security: 4 - Input sanitization (absint, sanitize_text_field, sanitize_key). Output escaping throughout. Pinecone endpoint HTTPS-only with domain allowlist. No sensitive data in index text.
- Operability: 4 - Per-context configurable indexing options (excerpt, URL, taxonomies, meta, WC fields). Admin UI for create/modify contexts with progress bars and filters.
- Extensibility: 4 - `aichat_index_text_parts` filter for custom index content. Configurable options. Embedding provider override filter. But no formal plugin hook for adding ingestion sources.

- Weighted Summary: 4.00 (adopt)
- Recommendation: adopt
- Residual Risks: No queue/retry for batch operations. Web scraper is add-on with beta maturity.

#### FEAT-MXC-RAG-001 - SRC-MXC

- Coverage: 5 - Broadest ingestion: manual text, single URL, XML sitemap (auto-detect), PDF, WP posts/pages/products/CPTs, ACF (15+ field types), WooCommerce metadata (pricing/SKU/categories/tabs/variable), bbPress. Best-in-class source diversity.
- Reliability: 4 - DB-backed queue with priority ordering and 3 retry attempts. Stalled processing detection (5-minute timeout). Browser-driven batch processing (5 concurrent). Auto-sync on post update/delete. ACF save integration.
- Security: 4 - Nonce verification on all AJAX handlers. Capability checks on admin operations. `$wpdb->prepare()` throughout. Content sanitization: UTF-8 validation, control char removal, 65K char limit. Role-based content access.
- Operability: 4 - Real-time progress bars. Error summary tables. Entry management with pagination/search/filter. Sitemap auto-detection UI. Queue completion verification.
- Extensibility: 3 - 30+ AJAX actions registered. WordPress post lifecycle hooks. But no explicit filter for extending ingestion sources. Monolithic class limits structural extension.

- Weighted Summary: 4.15 (adopt - highest ingestion score)
- Recommendation: adopt
- Residual Risks: Monolithic class (7286 lines) creates maintenance risk. Browser-driven queue requires open admin page.

#### FEAT-BAA-RAG-001 - SRC-BAA

- Coverage: 2 - WP posts/pages only (configurable types). No URL/sitemap/PDF/ACF/WooCommerce/uploaded files. Per-post exclusion meta. Auto-index on save_post.
- Reliability: 3 - Synchronous indexing is reliable for single posts. Bulk reindex via TRUNCATE is atomic. No queue, no retry, no batch. Simple and predictable.
- Security: 3 - `$wpdb->prepare()` for SQL. `$wpdb->esc_like()` for search. But missing capability checks in indexer class (relies on caller).
- Operability: 3 - Admin tab with stats, post type checkboxes, test search. Reindex All button. Simple but functional.
- Extensibility: 2 - Settings-driven post type configuration. Per-post exclusion meta. No hooks or filters for extending behavior.

- Weighted Summary: 2.55 (reject - below threshold)
- Recommendation: reject
- Residual Risks: No semantic search capability. No chunking or embedding. Cannot handle large content.

---

## Capability 2: Chunking and Indexing

Comparison of text chunking strategies and index storage mechanisms.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-RAG-002` | `SRC-AXI` | 4 | 4 | 4 | 4 | 4 | 4.00 | `adopt` |
| `FEAT-MXC-RAG-002` | `SRC-MXC` | 4 | 4 | 4 | 4 | 4 | 4.00 | `adopt` |
| `FEAT-BAA-RAG-003` | `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 | `reject` |

### Evidence Notes

#### FEAT-AXI-RAG-002 - SRC-AXI

- Coverage: 4 - Overlap-based chunking (1000 words target, 180 overlap). Full indexing pipeline: build text, chunk, embed per chunk, store in aichat_chunks table. Configurable per-context indexing options. Chunk storage includes post_id, context_id, chunk_index, type, title, content, embedding, tokens, timestamps.
- Reliability: 4 - Delete-then-insert ensures clean reindex per post. Token count tracked. Anomalous zero-embedding detection. DB insert error logging. Returns true if any chunk succeeded.
- Security: 4 - Embeddings stored as JSON in DB (not executable). Prepared statements for all DB operations. No user input in chunk content without sanitization.
- Operability: 4 - Per-context indexing options configurable via admin UI. Progress tracking during batch operations. Chunk count visible in context management.
- Extensibility: 4 - `aichat_index_text_parts` filter. Embedding provider override. Per-context configuration. But chunk size/overlap not admin-configurable.

- Weighted Summary: 4.00 (adopt)
- Recommendation: adopt
- Residual Risks: Chunk size/overlap not configurable in admin UI (code-level defaults). Overlap increases storage.

#### FEAT-MXC-RAG-002 - SRC-MXC

- Coverage: 4 - 3-tier hierarchical splitting (paragraph, sentence, word). Configurable chunk size (1000-10000 chars, default 4000). JSON metadata per chunk (document_type, chunk_index, total_chunks, source_url, parent_url_hash). Deterministic vector IDs. No overlap by design (reassembles at retrieval).
- Reliability: 4 - Static factory from WP settings. Input validation and clamping. Empty content returns empty array. Parse failures return original text. Metadata parsing with graceful fallback.
- Security: 4 - Input validation (intval, max/min clamping). ABSPATH check. No executable content in chunks.
- Operability: 4 - Chunk size configurable in admin UI. Enabled/disabled toggle. Chunk metadata visible in knowledge base. Grouped chunk display in entry browser.
- Extensibility: 4 - Settings-driven configuration. Static utility methods reusable. Deterministic vector IDs enable parent-child management.

- Weighted Summary: 4.00 (adopt)
- Recommendation: adopt
- Residual Risks: No overlap means some context lost at chunk boundaries (mitigated by reassembly). Different chunk size units (words vs chars) from SRC-AXI.

#### FEAT-BAA-RAG-003 - SRC-BAA

- Coverage: 0 - No chunking. Entire post content stored as single record. No embedding generation, no vector storage.
- Reliability: 0 - N/A -- capability not implemented.
- Security: 0 - N/A.
- Operability: 0 - N/A.
- Extensibility: 0 - N/A.

- Weighted Summary: 0.00 (reject - not present)
- Recommendation: reject
- Residual Risks: N/A capability. Cannot be used as baseline.

---

## Capability 3: Retrieval and Ranking

Comparison of search/similarity algorithms and result ranking mechanisms.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-RAG-003` | `SRC-AXI` | 5 | 4 | 4 | 4 | 4 | 4.30 | `adopt` |
| `FEAT-MXC-RAG-003` | `SRC-MXC` | 4 | 4 | 4 | 3 | 4 | 3.85 | `adopt` |
| `FEAT-BAA-RAG-002` | `SRC-BAA` | 2 | 3 | 3 | 3 | 2 | 2.55 | `reject` |

### Evidence Notes

#### FEAT-AXI-RAG-003 - SRC-AXI

- Coverage: 5 - 4 retrieval modes (local cosine, Pinecone, page, auto). Query-time WooCommerce enrichment. Local mode computes cosine similarity over all chunks. Pinecone with namespace isolation. Page mode for current-page context. Auto-detection from context config. Best modal coverage.
- Reliability: 4 - WP_Error handling for Pinecone. HTTP status code validation. Empty embedding detection. Provider mismatch handling. Returns empty array gracefully on failures.
- Security: 4 - Pinecone endpoint HTTPS-only with domain allowlist. Sanitized namespace. `$wpdb->prepare()` for local queries. No user input directly in vector queries.
- Operability: 4 - Context-level mode selection in admin UI. Top-K configurable. Live WC data enrichment at query time avoids reindexing.
- Extensibility: 4 - External embedding provider filter. Pinecone domain allowlist filter. Context-level configuration. But local retrieval algorithm not pluggable.

- Weighted Summary: 4.30 (adopt - strongest retrieval)
- Recommendation: adopt
- Residual Risks: Local retrieval is O(n) brute-force -- will not scale to large corpora (>10K chunks).

#### FEAT-MXC-RAG-003 - SRC-MXC

- Coverage: 4 - Multi-provider embeddings (OpenAI, Voyage AI, Gemini). Bot-specific API keys and models. Storage to WP DB or Pinecone. Pre-processing with UTF-8 validation and token estimation. Dimension normalization for cross-provider compatibility.
- Reliability: 4 - Pre-processing catches encoding issues before API calls. Token estimation prevents oversized requests. Bot-specific configuration prevents key conflicts.
- Security: 4 - Capability checks on all admin operations. Content sanitization before embedding. Nonce verification. Pinecone API key management.
- Operability: 3 - Embedding configuration in admin. But retrieval tuning less visible than SRC-AXI (query-time behavior embedded in integrator class).
- Extensibility: 4 - Multi-provider support. Bot-specific configuration. Pinecone namespace per bot. But retrieval algorithm embedded in monolithic integrator.

- Weighted Summary: 3.85 (adopt)
- Recommendation: adopt
- Residual Risks: Retrieval logic embedded in monolithic integrator class rather than standalone module.

#### FEAT-BAA-RAG-002 - SRC-BAA

- Coverage: 2 - MySQL FULLTEXT search only (keyword-based, no semantic understanding). LIKE fallback with priority weighting. Stop word filtering. Configurable result limit.
- Reliability: 3 - FULLTEXT search is well-understood MySQL feature. LIKE fallback adds resilience. Simple and predictable.
- Security: 3 - `$wpdb->prepare()` for queries. `$wpdb->esc_like()` for LIKE terms. No external API calls.
- Operability: 3 - Test search in admin. Max results configurable. But no retrieval quality metrics.
- Extensibility: 2 - No hooks. Result limit configurable but search algorithm not pluggable.

- Weighted Summary: 2.55 (reject)
- Recommendation: reject
- Residual Risks: No semantic search. FULLTEXT limitations with short queries. Cannot compete with vector-based retrieval.

---

## Capability 4: Context Assembly

Comparison of prompt construction and post-processing for LLM interactions.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-RAG-004` | `SRC-AXI` | 5 | 4 | 5 | 4 | 5 | 4.65 | `adopt` |
| `FEAT-MXC-RAG-004` | `SRC-MXC` | 3 | 4 | 3 | 4 | 3 | 3.35 | `investigate` |
| `FEAT-BAA-RAG-004` | `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 | `reject` |

### Evidence Notes

#### FEAT-AXI-RAG-004 - SRC-AXI

- Coverage: 5 - Full prompt construction system: security policy + datetime + user context + bot instructions with template tokens + MCP tools notice + numbered CONTEXT blocks + QUESTION + LINK markers + suggestion generation. Post-answer link injection with deduplication. Most comprehensive context assembly.
- Reliability: 4 - Context max_length truncation prevents prompt overflow. Instruction token resolution with schema validation. Error handling in function calls (try/catch). Link replacement graceful fallback.
- Security: 5 - Security policy always prepended (prevents API key disclosure, prompt injection resistance). Template tokens use allowlist registry (no eval). Safe function calls with schema validation, output sanitization, length truncation. `[LINK]` markers prevent raw URL exposure. Best security posture.
- Operability: 4 - Security policy customizable via options + filter. Bot instructions with template tokens. Context max_length configurable. Suggestion count configurable.
- Extensibility: 5 - Rich filter hooks: `aichat_system_prompt`, `aichat_security_policy`, `aichat_instruction_tokens_map`, `aichat_system_functions_registry`, `aichat_link_placeholder_markup`. Template tokens extensible.

- Weighted Summary: 4.65 (adopt - highest capability score in session)
- Recommendation: adopt
- Residual Risks: None significant. Well-designed and security-hardened.

#### FEAT-MXC-RAG-004 - SRC-MXC (scored via knowledge processing JS)

- Coverage: 3 - Knowledge pipeline UI with real-time monitoring. Browser-driven queue processing. Progress tracking. Entry management. But context assembly for prompts is embedded in integrator class, not a standalone capability.
- Reliability: 4 - Queue stall prevention. Completion verification. Error summary tracking. Concurrent batch processing.
- Security: 3 - Nonce-protected AJAX. No sensitive data client-side. But context assembly lacks dedicated security policy.
- Operability: 4 - Real-time progress bars. Error details. Entry count updates. Sitemap detection. Strong operational UX.
- Extensibility: 3 - jQuery-based. Event-driven. But tightly coupled to MxChat admin.

- Weighted Summary: 3.35 (investigate)
- Recommendation: investigate
- Residual Risks: Context assembly (prompt construction) is not a standalone module. Security policy for prompts is weaker than SRC-AXI.

#### FEAT-BAA-RAG-004 - SRC-BAA

- Coverage: 0 - No dedicated context assembly. Basic search results passed inline to provider.
- Reliability: 0 - N/A.
- Security: 0 - N/A.
- Operability: 0 - N/A.
- Extensibility: 0 - N/A.

- Weighted Summary: 0.00 (reject - not present)
- Recommendation: reject
- Residual Risks: N/A capability.

---

## Capability 5: Embedding Integration

Comparison of embedding provider architecture and management.

### Scored Comparison Table

| Feature ID | Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total | Band |
|---|---|---|---|---|---|---|---|---|
| `FEAT-AXI-RAG-005` | `SRC-AXI` | 4 | 4 | 4 | 4 | 5 | 4.15 | `adopt` |
| `FEAT-MXC-RAG-003` | `SRC-MXC` | 4 | 4 | 3 | 4 | 3 | 3.65 | `investigate` |
| `FEAT-BAA-RAG-003` | `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 | `reject` |

### Evidence Notes

#### FEAT-AXI-RAG-005 - SRC-AXI

- Coverage: 4 - OpenAI + Gemini + Claude fallback to OpenAI. Agency proxy mode. Per-context provider auto-detection and DB persistence. Available providers list with key status. 2 providers with actual embeddings (OpenAI, Gemini).
- Reliability: 4 - Returns null on failure (caller skips chunk). Debug logging. Provider resolution pipeline: preference > key-based auto-detect > Agency default.
- Security: 4 - API keys not exposed in embedding calls. Agency proxy abstracts key management. Provider names sanitized via `sanitize_key()`.
- Operability: 4 - Admin UI embedding provider selector. Per-context provider persistence. Available providers display with key status and model names.
- Extensibility: 5 - `aichat_generate_embedding_override` filter for external providers. `aichat_embedding_provider` filter. Agency proxy for centralized management. Strongest extensibility.

- Weighted Summary: 4.15 (adopt)
- Recommendation: adopt
- Residual Risks: Claude has no native embedding API (fallback to OpenAI is pragmatic but limits provider choice).

#### FEAT-MXC-RAG-003 - SRC-MXC

- Coverage: 4 - OpenAI + Voyage AI + Gemini. Bot-specific API key and model selection. Dimension normalization for cross-provider compatibility. 3 providers with actual embeddings.
- Reliability: 4 - UTF-8 validation and control char removal before API calls. Token estimation. Error tracking per phrase.
- Security: 3 - API keys from options (plaintext). Bot-specific key resolution. No encryption at rest.
- Operability: 4 - Embedding model selector in admin. Bot-specific configuration. Model browsing.
- Extensibility: 3 - Multiple providers via configuration. But no external provider filter hook. Adding provider requires code change.

- Weighted Summary: 3.65 (investigate)
- Recommendation: investigate
- Residual Risks: No external provider filter. API keys unencrypted. Adding new provider requires code change.

#### FEAT-BAA-RAG-003 - SRC-BAA

- Coverage: 0 - No embedding capability at all. Keyword-based only.
- Reliability: 0 - N/A.
- Security: 0 - N/A.
- Operability: 0 - N/A.
- Extensibility: 0 - N/A.

- Weighted Summary: 0.00 (reject - not present)
- Recommendation: reject
- Residual Risks: N/A capability.

---

## Aggregate Domain Scores

### Per-Source Weighted Averages (across all 5 scored capabilities)

| Source | DIM-COVERAGE | DIM-RELIABILITY | DIM-SECURITY | DIM-OPERABILITY | DIM-EXTENSIBILITY | Aggregate Weighted | Band |
|---|---|---|---|---|---|---|---|
| `SRC-AXI` | 4.4 | 4.0 | 4.2 | 4.0 | 4.4 | 4.22 | `adopt` |
| `SRC-MXC` | 4.0 | 4.0 | 3.6 | 3.8 | 3.4 | 3.80 | `adopt` |
| `SRC-BAA` | 0.8 | 1.2 | 1.2 | 1.2 | 0.8 | 1.02 | `reject` |

**Notes on aggregation**: Averages computed across 5 capabilities, giving equal weight to each. SRC-AXI leads in context assembly (4.65) and retrieval (4.30). SRC-MXC leads in ingestion breadth (4.15). SRC-BAA is below threshold in all capabilities due to keyword-only search without embeddings, chunking, or sophisticated context assembly.
