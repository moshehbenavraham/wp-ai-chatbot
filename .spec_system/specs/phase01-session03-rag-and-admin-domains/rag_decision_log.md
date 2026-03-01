# RAG Decision Log

## Log Metadata

| Field | Value |
|---|---|
| `log_id` | `DL-P01-S03-RAG` |
| `session_id` | `phase01-session03-rag-and-admin-domains` |
| `phase` | `1` |
| `domain_scope` | `["DOM-RAG"]` |
| `created_date` | `2026-03-01` |
| `last_updated` | `2026-03-01` |
| `context_pack_id` | `CP-P01-S03-001` |

---

## Entry: `DEC-RAG-001`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-RAG-001` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Ingestion pipeline` |
| `capability_summary` | Content ingestion system that extracts, cleans, and prepares content from multiple sources (WordPress content, uploaded files, web pages, PDFs, sitemaps) for embedding and indexing in the knowledge base. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-RAG-AXI-001` | `axiachat-ai/includes/contexto-functions.php` | `FEAT-AXI-RAG-001` | `implemented` |
| `SRC-MXC` | `ANC-RAG-MXC-001` | `mxchat-basic/admin/class-knowledge-manager.php` | `FEAT-MXC-RAG-001` | `implemented` |
| `SRC-BAA` | `ANC-RAG-BAA-001` | `baachal/includes/content-indexer.php` | `FEAT-BAA-RAG-001` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 4 | 4 | 4 | 4 | 4 | 4.00 |
| `SRC-MXC` | 5 | 4 | 4 | 4 | 3 | 4.15 |
| `SRC-BAA` | 2 | 3 | 3 | 3 | 2 | 2.55 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-MXC` |
| `recommendation_band` | `adopt` |
| `rationale` | MxChat has the broadest ingestion surface (8+ sources: manual text, URL, sitemap, PDF, WP content, ACF, WooCommerce, bbPress) with a production-quality DB-backed queue system (priority ordering, 3 retry attempts, stall detection). AxiaChat has strong page builder support (Elementor walker, shortcode expansion) and web scraping add-on but lacks sitemap/PDF/ACF ingestion. Modification: adopt MxChat's queue architecture and source breadth, integrate AxiaChat's content cleaning pipeline (Elementor support, shortcode handling), and web scraper module. |
| `modification_notes` | Merge SRC-MXC queue infrastructure with SRC-AXI content cleaning (Elementor, page builders). Port SRC-AXI web scraper as add-on module. Ensure per-context configurable indexing options (SRC-AXI pattern). |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-ADMIN", "DOM-PROVIDER"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `migration_notes` | Queue table schema from MxChat needs migration path. AxiaChat's aichat_chunks table structure should be evaluated against MxChat's storage pattern. Elementor walker is SRC-AXI exclusive and needs porting. |
| `carry_forward_ids` | `["CFI-RAG-001"]` |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-RAG-002`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-RAG-002` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Chunking and indexing` |
| `capability_summary` | Text chunking strategy that splits content into appropriately sized pieces for embedding generation and storage, with metadata tracking for chunk relationships. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-RAG-AXI-001` | `axiachat-ai/includes/contexto-functions.php` | `FEAT-AXI-RAG-002` | `implemented` |
| `SRC-MXC` | `ANC-RAG-MXC-001` | `mxchat-basic/includes/class-mxchat-chunker.php` | `FEAT-MXC-RAG-002` | `implemented` |
| `SRC-BAA` | `ANC-RAG-BAA-001` | `baachal/includes/content-indexer.php` | `FEAT-BAA-RAG-003` | `na_no_matching_implementation` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 4 | 4 | 4 | 4 | 4 | 4.00 |
| `SRC-MXC` | 4 | 4 | 4 | 4 | 4 | 4.00 |
| `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | Both SRC-AXI and SRC-MXC score identically at 4.00. Tie-break rule 1 (DIM-SECURITY) shows no difference (both 4). Tie-break rule 2 (dependency_profile) shows no difference (both plugin_internal_modules). Tie-break rule 3 (migration compatibility) favors SRC-AXI: overlap-based chunking preserves more context at boundaries, and the chunk storage pattern (aichat_chunks table with full metadata) is cleaner than MxChat's inline JSON metadata prefix approach. Modification: adopt SRC-AXI's overlap-based chunking with admin-configurable chunk size from SRC-MXC, and add MxChat's chunk metadata tagging for parent-child relationships. |
| `modification_notes` | Add admin-configurable chunk size/overlap (SRC-MXC pattern). Adopt SRC-MXC's deterministic vector ID format for chunk management. Use SRC-AXI's separate DB columns rather than SRC-MXC's inline JSON metadata prefix for cleaner data access. |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-ADMIN"]` |
| `risk_level` | `low` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `migration_notes` | Chunk table schema needs to incorporate deterministic vector IDs from MxChat for Pinecone integration. Admin UI needs chunk size/overlap configuration fields. |
| `carry_forward_ids` | `["CFI-RAG-002"]` |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `yes` |
| `rule_used` | `migration_compatibility` |
| `rationale` | With identical scores across all dimensions, SRC-AXI's overlap-based chunking provides better context preservation at boundaries, and its separate DB column storage is cleaner than SRC-MXC's inline JSON metadata prefix approach. |

---

## Entry: `DEC-RAG-003`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-RAG-003` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Retrieval and ranking` |
| `capability_summary` | Search and similarity mechanisms that find relevant content chunks for a user query, rank them by relevance, and return top-K results for context assembly. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-RAG-AXI-001` | `axiachat-ai/includes/contexto-functions.php` | `FEAT-AXI-RAG-003` | `implemented` |
| `SRC-MXC` | `ANC-RAG-MXC-001` | `mxchat-basic/admin/class-knowledge-manager.php` | `FEAT-MXC-RAG-003` | `implemented` |
| `SRC-BAA` | `ANC-RAG-BAA-001` | `baachal/includes/content-indexer.php` | `FEAT-BAA-RAG-002` | `implemented` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 5 | 4 | 4 | 4 | 4 | 4.30 |
| `SRC-MXC` | 4 | 4 | 4 | 3 | 4 | 3.85 |
| `SRC-BAA` | 2 | 3 | 3 | 3 | 2 | 2.55 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat has the strongest retrieval architecture with 4 modes (local cosine, Pinecone, page, auto), query-time WooCommerce enrichment, and Pinecone endpoint security (HTTPS-only, domain allowlist). The mode-based architecture cleanly separates retrieval strategies. MxChat's embedding generation pipeline (multi-provider, bot-specific) is stronger. Modification: adopt SRC-AXI's mode-based retrieval architecture, integrate SRC-MXC's multi-provider embedding pipeline with bot-specific keys, and plan for ANN index replacement of brute-force O(n) local retrieval in Phase 02. |
| `modification_notes` | Replace brute-force local retrieval with ANN index (Phase 02 architecture decision). Integrate SRC-MXC's Voyage AI provider support. Keep SRC-AXI's Pinecone security controls. Add SRC-MXC's dimension normalization for cross-provider compatibility. |
| `carry_forward_ids` | `["CFI-RAG-003"]` |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-PROVIDER", "DOM-ADMIN"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `optional_external_service` |
| `migration_notes` | Local retrieval scalability is a Phase 02 architecture concern. Pinecone integration from both sources should be unified. Voyage AI provider needs new embedding adapter. |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-RAG-004`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-RAG-004` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Context assembly` |
| `capability_summary` | Prompt construction system that formats retrieved context, system instructions, and user query into a structured LLM prompt with security policies, template tokens, and post-answer processing. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-RAG-AXI-001` | `axiachat-ai/includes/contexto-functions.php` | `FEAT-AXI-RAG-004` | `implemented` |
| `SRC-MXC` | `ANC-RAG-MXC-001` | `mxchat-basic/js/knowledge-processing.js` | `FEAT-MXC-RAG-004` | `implemented` |
| `SRC-BAA` | `ANC-RAG-BAA-001` | `baachal/includes/content-indexer.php` | `FEAT-BAA-RAG-004` | `na_no_matching_implementation` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 5 | 4 | 5 | 4 | 5 | 4.65 |
| `SRC-MXC` | 3 | 4 | 3 | 4 | 3 | 3.35 |
| `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_as_is` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat's context assembly is the clear winner and highest-scored capability in the RAG domain (4.65). It provides comprehensive prompt construction: security policy injection, datetime awareness, user context, template tokens with safe function calls, MCP tools integration, numbered context blocks with LINK markers, suggestion generation, and context length management. The security dimension (5) reflects always-on security policy, allowlist-based template resolution (no eval), and output sanitization. No other source approaches this level of sophistication. Adopt as-is. |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-SECURITY", "DOM-PROVIDER", "DOM-ADMIN"]` |
| `risk_level` | `low` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `migration_notes` | Context assembly is self-contained. MxChat's prompt construction (embedded in integrator) should be replaced by this module. Template token system extensible for future needs. |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Entry: `DEC-RAG-005`

### Identification

| Field | Value |
|---|---|
| `decision_id` | `DEC-RAG-005` |
| `domain_id` | `DOM-RAG` |
| `capability_name` | `Embedding integration` |
| `capability_summary` | Multi-provider embedding generation with provider management, key resolution, and per-context provider persistence. |

### Source Anchors

| Source | Anchor ID | Source Path | Feature ID | Implementation Status |
|---|---|---|---|---|
| `SRC-AXI` | `ANC-RAG-AXI-001` | `axiachat-ai/includes/contexto-functions.php` | `FEAT-AXI-RAG-005` | `implemented` |
| `SRC-MXC` | `ANC-RAG-MXC-001` | `mxchat-basic/admin/class-knowledge-manager.php` | `FEAT-MXC-RAG-003` | `implemented` |
| `SRC-BAA` | `ANC-RAG-BAA-001` | `baachal/includes/content-indexer.php` | `FEAT-BAA-RAG-003` | `na_no_matching_implementation` |

### Scoring Summary

| Source | DIM-COVERAGE (30%) | DIM-RELIABILITY (20%) | DIM-SECURITY (20%) | DIM-OPERABILITY (15%) | DIM-EXTENSIBILITY (15%) | Weighted Total |
|---|---|---|---|---|---|---|
| `SRC-AXI` | 4 | 4 | 4 | 4 | 5 | 4.15 |
| `SRC-MXC` | 4 | 4 | 3 | 4 | 3 | 3.65 |
| `SRC-BAA` | 0 | 0 | 0 | 0 | 0 | 0.00 |

### Outcome

| Field | Value |
|---|---|
| `outcome` | `adopt_with_modifications` |
| `winner` | `SRC-AXI` |
| `recommendation_band` | `adopt` |
| `rationale` | AxiaChat has the strongest embedding architecture with external provider filter (`aichat_generate_embedding_override`), per-context provider persistence, and Agency proxy support. The extensibility score (5) reflects the ability to add providers without code changes. MxChat adds Voyage AI support (absent from SRC-AXI) and bot-specific key resolution. Modification: adopt SRC-AXI's provider architecture with its override filter, add SRC-MXC's Voyage AI provider and bot-specific key resolution. |
| `modification_notes` | Add Voyage AI as a supported embedding provider. Integrate SRC-MXC's bot-specific API key resolution pattern. Keep SRC-AXI's override filter and Agency proxy support. Add SRC-MXC's dimension normalization for cross-provider compatibility. |
| `carry_forward_ids` | `["CFI-RAG-004"]` |

### Impact Assessment

| Field | Value |
|---|---|
| `affected_domains` | `["DOM-PROVIDER", "DOM-ADMIN"]` |
| `risk_level` | `medium` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `external_ai_provider_required` |
| `migration_notes` | Embedding provider architecture should align with provider domain registry pattern (DEC-PRO-001). Voyage AI adapter is net-new. Bot-specific key resolution extends per-context persistence. |

### Tie-Break

| Field | Value |
|---|---|
| `tie_break_applied` | `no` |

---

## Carry-Forward Items

### CFI-RAG-001

| Field | Value |
|---|---|
| `item_id` | `CFI-RAG-001` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session03-rag-and-admin-domains` |
| `domain_id` | `DOM-RAG` |
| `description` | Merge MxChat's queue-based ingestion infrastructure (priority ordering, retry, stall detection) with AxiaChat's content cleaning pipeline (Elementor walker, shortcode expansion, web scraper). Design unified queue table schema. |
| `priority` | `high` |
| `status` | `open` |
| `decision_id` | `DEC-RAG-001` |
| `related_anchor_ids` | `["ANC-RAG-AXI-001", "ANC-RAG-MXC-001"]` |
| `resolution_criteria` | Unified ingestion pipeline processes WP content, URLs, sitemaps, PDFs, ACF, and web-scraped content through a single queue with retry and progress tracking. Elementor/page builder content cleaning integrated. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-RAG-002

| Field | Value |
|---|---|
| `item_id` | `CFI-RAG-002` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session03-rag-and-admin-domains` |
| `domain_id` | `DOM-RAG` |
| `description` | Design unified chunking module with overlap-based splitting (SRC-AXI), admin-configurable chunk size (SRC-MXC), deterministic vector IDs (SRC-MXC), and clean DB column storage (SRC-AXI). |
| `priority` | `medium` |
| `status` | `open` |
| `decision_id` | `DEC-RAG-002` |
| `related_anchor_ids` | `["ANC-RAG-AXI-001", "ANC-RAG-MXC-001"]` |
| `resolution_criteria` | Chunker produces overlap-based chunks with configurable size/overlap, deterministic vector IDs, and metadata stored in dedicated DB columns. Admin UI exposes chunk size and overlap configuration. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-RAG-003

| Field | Value |
|---|---|
| `item_id` | `CFI-RAG-003` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session03-rag-and-admin-domains` |
| `domain_id` | `DOM-RAG` |
| `description` | Replace brute-force O(n) local cosine similarity with ANN index for scalable retrieval. Evaluate options: SQLite VSS, HNSW in PHP, or mandatory Pinecone for production. Keep mode-based architecture (local/Pinecone/page/auto). |
| `priority` | `high` |
| `status` | `open` |
| `decision_id` | `DEC-RAG-003` |
| `related_anchor_ids` | `["ANC-RAG-AXI-001"]` |
| `resolution_criteria` | Local retrieval scales to 50K+ chunks without degrading response time. Architecture decision documented with benchmark data. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

### CFI-RAG-004

| Field | Value |
|---|---|
| `item_id` | `CFI-RAG-004` |
| `item_type` | `deferred_decision` |
| `source_session` | `phase01-session03-rag-and-admin-domains` |
| `domain_id` | `DOM-RAG` |
| `description` | Unify embedding provider architecture: add Voyage AI adapter, integrate bot-specific key resolution, add dimension normalization for cross-provider compatibility, align with provider domain registry (DEC-PRO-001). |
| `priority` | `medium` |
| `status` | `open` |
| `decision_id` | `DEC-RAG-005` |
| `related_anchor_ids` | `["ANC-RAG-AXI-001", "ANC-RAG-MXC-001"]` |
| `resolution_criteria` | Embedding generation works via provider registry pattern with override filter. Voyage AI adapter registered. Bot-specific keys resolved. Cross-provider dimension normalization applied. |
| `target_phase` | `2` |
| `created_date` | `2026-03-01` |

---

## Cross-Domain Interaction Notes

### DOM-RAG x DOM-PROVIDER

- Embedding generation is a direct dependency on provider APIs (OpenAI, Gemini, Voyage AI). Embedding provider architecture should align with provider registry pattern from DEC-PRO-001.
- Context assembly references MCP tools and provider-specific behaviors. Provider adapter interface affects how context is formatted for each provider.
- Agency proxy mode bridges RAG embedding calls through centralized provider key management.

### DOM-RAG x DOM-ADMIN

**Interaction Points (T015)**:

1. **Ingestion triggers**: Admin UI initiates content ingestion -- SRC-AXI's context create/modify pages trigger per-post indexing; SRC-MXC's Knowledge page provides queue processing start/stop/retry controls; SRC-BAA's Content tab has a reindex button. The unified plugin needs an admin surface to trigger, pause, and monitor ingestion across all source types.

2. **Index status and monitoring**: Admin must display current index state -- SRC-MXC shows entry counts with 3-second polling, queue progress with per-item status, and error summary tables; SRC-AXI shows per-context document counts; SRC-BAA shows post count only. RAG pipeline health (indexed count, queue depth, last error) should appear in admin diagnostics (CFI-ADM-003).

3. **Chunking configuration**: Chunk size and overlap are RAG parameters exposed via admin UI -- SRC-MXC has admin-settable chunk size (1000-10000 chars); SRC-AXI uses fixed 1000-word/180-word overlap. Per DEC-RAG-002, the unified chunker needs admin-configurable chunk size and overlap fields in the RAG settings page.

4. **Retrieval tuning**: Admin controls RAG retrieval behavior -- SRC-AXI has per-context retrieval mode selection (local/Pinecone/page/auto) and similarity threshold; SRC-MXC has similarity threshold (10-95%) and RAG source count (3-10). These RAG tuning parameters need admin UI fields with appropriate range validation (DEC-ADM-002 sanitization).

5. **Embedding provider selection**: Embedding provider is selected via admin UI -- SRC-AXI stores per-context embedding provider preference; SRC-MXC has bot-specific embedding model selection. This overlaps with DOM-PROVIDER API key management (DEC-ADM-002) and requires the admin to show only providers with valid API keys.

6. **Knowledge entry management**: Admin provides CRUD for knowledge base entries -- SRC-MXC has grouped chunk display, pagination, search/filter, bulk delete, and role-based access restrictions; SRC-AXI has per-context document management with add/remove. Entry management is an admin page that operates directly on RAG storage.

7. **Auto-sync controls**: Admin toggles automatic RAG synchronization -- SRC-MXC has auto-sync on post update/delete with ACF `save_post` integration; SRC-BAA has auto-index toggle. Admin operational controls (DEC-ADM-004) must include RAG auto-sync configuration.

8. **Security policy injection**: Admin-configurable security policy text (SRC-AXI) is injected into RAG context assembly (DEC-RAG-004). The admin settings page for security policy directly affects RAG prompt construction. Customizable text with reset-to-defaults is an admin operational control that produces a RAG behavioral output.

### DOM-RAG x DOM-SECURITY

- Security policy injection in context assembly prevents API key disclosure and prompt injection.
- Template tokens use allowlist registry (no eval) to prevent code execution.
- Role-based content access (SRC-MXC) restricts knowledge base content by user role.
- Pinecone endpoint sanitized with HTTPS-only and domain allowlist.

---

## Decision Summary

| Decision ID | Capability | Winner | Outcome | Band | Score |
|---|---|---|---|---|---|
| `DEC-RAG-001` | Ingestion pipeline | `SRC-MXC` | `adopt_with_modifications` | `adopt` | 4.15 |
| `DEC-RAG-002` | Chunking and indexing | `SRC-AXI` | `adopt_with_modifications` | `adopt` | 4.00 |
| `DEC-RAG-003` | Retrieval and ranking | `SRC-AXI` | `adopt_with_modifications` | `adopt` | 4.30 |
| `DEC-RAG-004` | Context assembly | `SRC-AXI` | `adopt_as_is` | `adopt` | 4.65 |
| `DEC-RAG-005` | Embedding integration | `SRC-AXI` | `adopt_with_modifications` | `adopt` | 4.15 |

### Domain Summary

| Metric | Value |
|--------|-------|
| Total Capabilities Scored | 5 |
| Adopt-As-Is | 1 |
| Adopt-With-Modifications | 4 |
| Reject/Defer | 0 |
| Domain Winner | `SRC-AXI` (4 of 5 capabilities) |
| Dominant Maturity | `production_stable` |
| Key Risks | O(n) local retrieval scalability; MxChat queue integration; Voyage AI adapter creation; chunker unification |
| Cross-Domain Dependencies | `DOM-PROVIDER`, `DOM-ADMIN`, `DOM-SECURITY` |
| Carry-Forward Items | `CFI-RAG-001`, `CFI-RAG-002`, `CFI-RAG-003`, `CFI-RAG-004` |
