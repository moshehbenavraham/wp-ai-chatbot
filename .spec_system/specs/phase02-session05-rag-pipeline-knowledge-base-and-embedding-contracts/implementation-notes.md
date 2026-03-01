# Implementation Notes

**Session ID**: `phase02-session05-rag-pipeline-knowledge-base-and-embedding-contracts`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Session Summary

This session produced seven contract documents defining the complete RAG pipeline:
content source adapters, ingestion queue, chunking module, embedding providers, retrieval and
ranking, context assembly, and knowledge admin surface. All four RAG carry-forward items
(CFI-RAG-001 through CFI-RAG-004) from Phase 01 are resolved.

The dominant pattern source is AxiaChat (winner in 4 of 5 RAG capabilities per Phase 01 scoring).
MxChat contributes queue infrastructure and source breadth. Baachal contributes nothing to the
RAG subsystem (score 0.00 in chunking/embedding, below adoption threshold for retrieval).

---

## 2. CFI-RAG Resolution Table

| CFI Item | Description | Resolved In | Status |
|----------|-------------|-------------|--------|
| CFI-RAG-001 | Unified ingestion pipeline (MXC queue + AXI content cleaning) | `content_source_adapter_interface.md` + `ingestion_queue_spec.md` | RESOLVED |
| CFI-RAG-002 | Unified chunking module (AXI overlap + MXC configurable size + MXC deterministic IDs) | `chunking_module_interface.md` | RESOLVED |
| CFI-RAG-003 | ANN index scalability decision | `retrieval_ranking_contract.md` Section 5 | RESOLVED |
| CFI-RAG-004 | Unified embedding provider architecture (AXI + Voyage AI + bot-specific keys) | `embedding_provider_interface.md` | RESOLVED |

---

## 3. Key Architectural Decisions

### Decision 1: ANN Index -- Progressive Architecture (CFI-RAG-003)

**Chosen**: Local cosine similarity (MVP, <10K chunks) + Pinecone (production, no chunk limit).
**Rejected**: SQLite VSS, HNSW PHP, Redis RediSearch.
**Rationale**: No ANN library is viable for standard shared WP hosting. Brute-force cosine is
sufficient for sites up to ~10,000 chunks (practical PHP memory limit). Pinecone is the clear
production path. The `wpaic_retriever_local_search` filter enables third-party ANN plugins
without core changes.

### Decision 2: Embedding_Interface Separated from Provider_Interface

**Chosen**: Independent `Embedding_Interface` with `embed()` method, registered via provider
registry with `supports_embedding` capability flag.
**Rationale**: Method signatures differ fundamentally. Voyage AI is embedding-only (no chat).
Claude is chat-only (no embedding). The separation is architecturally honest and avoids force-fitting
different capabilities into one interface while maintaining the unified registry (RES-XD-008).

### Decision 3: Context Assembly Adopted As-Is (DEC-RAG-004)

**Chosen**: AxiaChat context assembly verbatim -- security policy, CONTEXT blocks, LINK markers,
template tokens.
**Rationale**: Highest scored RAG capability (4.65) by significant margin. MxChat has no
standalone context assembly module. The security-first design (always-on policy, allowlist tokens)
is the correct architecture.

### Decision 4: WP-Cron Worker Over Browser-Driven Processing

**Chosen**: WP-Cron scheduled event (`wpaic_rag_process_queue`, 3-minute interval).
**Rejected**: Browser-driven processing (MXC pattern -- requires admin page to stay open).
**Rationale**: Production reliability. WP-Cron fires regardless of whether an admin is logged in.
The MXC browser-driven approach is a known reliability anti-pattern for large indexing operations.

### Decision 5: Chunk Storage in Separate DB Columns (Not Inline JSON Prefix)

**Chosen**: AXI column pattern -- `title`, `content`, `type`, `chunk_index` as dedicated columns.
**Rejected**: MXC inline JSON metadata prefix (metadata embedded at start of content string).
**Rationale**: Separate columns enable direct SQL access to metadata without JSON parsing.
Simpler queries, cleaner schema, matches session 02 `wp_wpaic_chunks` table design.

---

## 4. Design Cross-References

| Contract | Consumes from Session 02 | Consumes from Session 03 |
|----------|--------------------------|--------------------------|
| Content source adapter | `wp_wpaic_chunks` (storage target) | `wpaic_register_providers` hook pattern |
| Ingestion queue | `wp_wpaic_queue` (full schema), `wp_wpaic_knowledge` (status) | None |
| Chunking module | `wp_wpaic_chunks` (upsert format, unique index) | None |
| Embedding interface | `wp_wpaic_chunks.embedding` (LONGTEXT JSON), `wp_wpaic_knowledge.embedding_*` | `Provider_Interface` pattern, `wpaic_register_providers`, compliance rules |
| Retrieval contract | `wp_wpaic_chunks` (query), `wp_wpaic_knowledge.context_type` | None |
| Context assembly | `wp_wpaic_bots.instructions`, `wp_wpaic_bots.model` | `Provider_Interface::chat()` (receives assembled prompt) |
| Knowledge admin | All RAG tables | None |

---

## 5. Lessons Learned (Carried Forward)

- **Anchor-first extraction** (from Session 01): Every interface cites concrete source anchors.
  Phase 06 implementors can proceed without re-reading full source repositories.
- **Explicit N/A policy** (from Session 01): Baachal has no RAG capability beyond keyword search.
  Documented as N/A throughout rather than forcing comparisons.
- **Progressive design** (new): For infrastructure features where the ideal solution has hard
  deployment constraints (ANN libraries on shared hosting), design for progressive enablement:
  simple baseline + optional advanced path. This is better than mandating complex dependencies.

---

## 6. Recommendations for Phase 06 Implementors

1. **Implement `WP_Post_Adapter` first** -- handles the most common case and validates the full
   pipeline end-to-end. Once working, other adapters follow the same pattern.

2. **Use `ON DUPLICATE KEY UPDATE embedding = NULL`** in chunk upserts. Setting embedding to null
   triggers re-embedding on the next worker tick. This is the correct strategy for reindex-on-edit.

3. **Test stall detection first** in the queue worker. Simulate a crash after `status='processing'`
   is set to confirm stall recovery works correctly.

4. **The `wpaic_generate_embedding_override` filter is critical** for sites with custom embedding
   providers (Agency proxy, internal models). Document it prominently in Phase 11 docs.

5. **Do not add auto-sync to `save_post` during activation** -- only register the hook when at
   least one knowledge context has `autosync_enabled = 1`. Check before enqueueing.

6. **Pinecone upsert is separate from Pinecone query**. The queue worker upserts chunks to Pinecone
   as part of the embedding phase. The Retriever queries Pinecone at request time. Do not confuse
   these two operations.

---

## 7. Open Questions for Phase 06

1. Should the URL adapter support custom HTTP headers (authentication for private pages)?
   Decision deferred -- implement basic HTTP only for MVP.
2. Sitemap adapter: support recursive sitemaps (sitemap index files)? Recommend yes for MVP.
3. Should `test_search` in admin generate a fresh embedding for the test query (costs money)
   or use a cached test embedding? Recommend fresh embedding with admin notice about cost.
