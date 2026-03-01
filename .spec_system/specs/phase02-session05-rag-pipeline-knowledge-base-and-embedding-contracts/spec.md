# Session Specification

**Session ID**: `phase02-session05-rag-pipeline-knowledge-base-and-embedding-contracts`
**Phase**: 02 - Target Architecture and Module Contracts
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Session Overview

This session designs the full RAG (Retrieval-Augmented Generation) pipeline contracts: content source
adapters, ingestion queue, chunking module, embedding providers, retrieval and ranking, context
assembly, and knowledge base admin surface. These contracts are the architectural backbone for Phase 06
implementation and are consumed by the chat runtime (session 04) whenever `context_mode = 'embeddings'`.

The session resolves all four RAG carry-forward items from Phase 01: CFI-RAG-001 (unified ingestion
pipeline), CFI-RAG-002 (unified chunking module), CFI-RAG-003 (ANN index scalability decision),
and CFI-RAG-004 (embedding provider architecture). It builds on the database schemas from session 02
(`wp_wpaic_knowledge`, `wp_wpaic_chunks`, `wp_wpaic_queue`) and the provider interface pattern from
session 03, which is reused for embedding providers.

Phase 01 analysis identified AXI as the domain winner for chunking (DEC-RAG-002), retrieval
(DEC-RAG-003), context assembly (DEC-RAG-004), and embedding architecture (DEC-RAG-005). MXC wins
ingestion breadth (DEC-RAG-001). BAA has no semantic RAG capability (score 0.00 in chunking/embedding).
This session resolves the merge strategy for all five capabilities into a coherent, secure, and
progressively scalable pipeline.

---

## 2. Objectives

1. Define a content source adapter interface that supports all Phase 01 in-scope sources (WP posts,
   pages, products, manual text, URLs, sitemaps, PDFs, ACF fields) via a pluggable registry.
2. Specify the ingestion queue system that wraps `wp_wpaic_queue`, drives cron-based workers, handles
   retry/backoff, and reports per-item status (resolves CFI-RAG-001).
3. Produce a chunking module interface that merges AXI's overlap-based splitting with MXC's
   admin-configurable chunk size and MXC's deterministic vector IDs (resolves CFI-RAG-002).
4. Define an `Embedding_Interface` that separates embedding providers from chat providers, registers
   via the provider registry capability flag, and supports batch requests (resolves CFI-RAG-004).
5. Specify retrieval modes (local, pinecone, page, auto) with a documented ANN scalability decision:
   progressive design -- local cosine for MVP (<10K chunks), Pinecone for production (resolves
   CFI-RAG-003).
6. Design context assembly following AXI's security-policy-first, CONTEXT-block, LINK-marker pattern
   with configurable token budgets and extensibility hooks.
7. Produce knowledge admin contracts (CRUD endpoints, queue dashboard, source management, test search)
   at a level of detail sufficient for parallel admin implementation in Phase 06.

---

## 3. Prerequisites

### Required Sessions

- [x] `phase02-session01-module-map-directory-architecture-and-bootstrap` -- Module map with `includes/rag/`
  location, `includes/provider/interface-embedding.php`, `wpaic_` hook prefix, naming conventions,
  dependency graph (rag depends on provider, security; runtime depends on rag)
- [x] `phase02-session02-data-layer-schema-design-and-migration-framework` -- `wp_wpaic_knowledge`,
  `wp_wpaic_chunks`, `wp_wpaic_queue` table schemas with all column definitions and index strategy
- [x] `phase02-session03-provider-orchestration-and-model-registry-contracts` -- `Provider_Interface`,
  `Streamable_Provider_Interface`, provider registry dispatch pattern, compliance rules (CR-01--CR-05),
  configuration injection via `$config` array; reused verbatim for `Embedding_Interface`

### Required Knowledge

- Phase 01 DOM-RAG winner summary: DEC-RAG-001 (MXC ingestion wins), DEC-RAG-002 (AXI chunking wins
  tie-break), DEC-RAG-003 (AXI retrieval 4 modes), DEC-RAG-004 (AXI context assembly 4.65 -- adopt
  as-is), DEC-RAG-005 (AXI embedding with MXC modifications)
- Carry-forward items: CFI-RAG-001 through CFI-RAG-004 (all open, all resolved here)
- Cross-domain resolution RES-XD-007 (WooCommerce-RAG boundary: product retrieval deferred to Phase 08)
- Cross-domain resolution RES-XD-008 (embedding providers register through provider registry)

### Environment Requirements

- Access to reference plugin source code (read-only)
- Session 01-04 deliverable documents in `.spec_system/specs/`

---

## 4. Scope

### In Scope (MVP)

- Content source adapter interface and registration contract (`Source_Adapter_Interface`)
- Ingestion queue worker design (cron-based), item lifecycle, retry policy, stall detection
- Unified chunker module (`Chunker` class): overlap-based splitting, configurable size/overlap,
  deterministic chunk IDs, metadata extraction
- Embedding provider interface (`Embedding_Interface`): separate from `Provider_Interface`, batch
  support, dimension metadata, registered via provider registry capability flag
- Four retrieval modes: `local` (PHP cosine similarity), `pinecone` (external vector DB),
  `page` (current-page context only), `auto` (mode resolved from knowledge context config)
- ANN scalability decision and documented trade-off matrix
- Context assembly specification: security policy injection, numbered CONTEXT blocks, LINK markers,
  token budget management, template token allowlist, suggestion generation hook
- Knowledge admin contracts: REST endpoints for knowledge CRUD, queue dashboard, source management,
  test-search console, bulk operations

### Out of Scope (Deferred)

- WooCommerce product-specific retrieval enrichment -- *Reason: RES-XD-007, Phase 08 scope*
- Web scraper implementation details (content extraction from URLs) -- *Reason: Phase 06 implementation*
- Actual embedding model selection or dimension sizes -- *Reason: Phase 06 implementation detail*
- SQLite VSS or HNSW PHP library integration -- *Reason: CFI-RAG-003 resolved as "not required for MVP"*
- PDF parsing implementation -- *Reason: Phase 06, add-on module scope*
- Full-text search (BAA FULLTEXT pattern) -- *Reason: below scoring threshold; vector search is the MVP path*
- Multi-tenancy / per-subsite knowledge bases -- *Reason: post-MVP*

---

## 5. Technical Approach

### Architecture Overview

The RAG pipeline is a four-stage linear system anchored in `includes/rag/`:

```
[Content Sources] --> [Ingestion Queue] --> [Chunker + Embedding] --> [wp_wpaic_chunks]
                                                                            |
[User Query] --> [Embedding_Client] --> [Retriever] --> [Context_Assembler] --> [Chat Pipeline]
```

Stage 1 (Ingest): Source adapters extract raw text; content pushed to `wp_wpaic_queue`.
Stage 2 (Process): Cron worker dequeues items, chunks text, generates embeddings, stores in
  `wp_wpaic_chunks`.
Stage 3 (Retrieve): Query embedded at runtime; `Retriever` fetches top-K relevant chunks.
Stage 4 (Assemble): `Context_Assembler` builds prompt with security policy + CONTEXT blocks.

### Design Patterns

- **Adapter registry**: Source adapters register via `wpaic_register_rag_sources` action hook
  (AXI extensibility pattern from `aichat_generate_embedding_override`)
- **Queue-worker**: Cron-driven background worker pulls one batch per tick, respects max_attempts
  and stall detection (MXC `mxchat_processing_queue` pattern)
- **Progressive ANN**: Local cosine is the MVP retrieval path (bounded by chunk count);
  Pinecone is the production path (no mandatory ANN library)
- **Security-first context**: AXI context assembly pattern with always-on security policy,
  template token allowlist, LINK markers to prevent raw URL exposure

### Technology Stack

- PHP >=7.4 (WordPress plugin backend)
- `wp_wpaic_chunks` table for local embedding storage (JSON float arrays)
- `wp_wpaic_queue` table for ingestion job management
- WP-Cron for background processing
- WordPress REST API for admin knowledge management endpoints
- Optional: Pinecone API (HTTPS, domain-allowlisted) for production-scale retrieval

---

## 6. Deliverables

### Files to Create

| File | Purpose | Est. Lines |
|------|---------|------------|
| `deliverables/content_source_adapter_interface.md` | Source adapter registration, discovery, extraction contract | ~350 |
| `deliverables/ingestion_queue_spec.md` | Queue model, worker lifecycle, retry policy, status tracking | ~400 |
| `deliverables/chunking_module_interface.md` | Chunker input/output, overlap strategy, deterministic IDs | ~350 |
| `deliverables/embedding_provider_interface.md` | `Embedding_Interface` contract, batch support, registry integration | ~400 |
| `deliverables/retrieval_ranking_contract.md` | Retrieval modes, scoring, ANN decision, result format | ~450 |
| `deliverables/context_assembly_spec.md` | Prompt construction rules, security policy, token budget, hooks | ~400 |
| `deliverables/knowledge_admin_contract.md` | REST endpoints, CRUD, queue dashboard, test-search | ~450 |
| `implementation-notes.md` | Session summary, decisions, CFI resolution table | ~150 |

### Files to Modify

| File | Changes | Est. Lines |
|------|---------|------------|
| `.spec_system/state.json` | Add session to `completed_sessions`, update `current_session` | ~5 |

---

## 7. Success Criteria

### Functional Requirements

- [x] Ingestion pipeline supports all Phase 01 in-scope content sources via adapter registry
- [x] Chunking module merges AXI overlap-based splitting with MXC configurable size and deterministic IDs
- [x] Embedding provider interface is pluggable: local (no-op mock), OpenAI, Voyage AI, Gemini
- [x] Context assembly enforces security policy injection (no sensitive content, no raw URLs in prompts)
- [x] ANN index design is optional/progressive: local cosine baseline, Pinecone production path
- [x] CFI-RAG-001 through CFI-RAG-004 fully resolved in contracts

### Testing Requirements

- [x] Contracts cross-referenced against CFI-RAG-001 through CFI-RAG-004 for completeness
- [x] Retrieval mode contracts trace end-to-end: query -> embed -> retrieve -> assemble -> prompt
- [x] Every admin endpoint has: route, method, auth, capability, request schema, response schema, error codes

### Non-Functional Requirements

- [x] All contracts reference session 02 table schemas and session 03 provider contracts explicitly
- [x] Every deliverable cites reference anchors from at least 2 of 3 source plugins
- [x] Security policy injection is non-optional and clearly documented

### Quality Gates

- [x] All files ASCII-encoded
- [x] Unix LF line endings
- [x] Consistent `wpaic_` prefix for all hook, option, and endpoint names
- [x] File paths match session 01 module map conventions (`includes/rag/`, `includes/provider/`)

---

## 8. Implementation Notes

### Key Considerations

- The RAG module is at dependency level 2 in the module graph (depends on provider for embedding,
  depends on security for policy injection). The runtime (level 3) depends on RAG -- this one-way
  dependency must be preserved. RAG never imports from runtime.
- Embeddings are stored as JSON float arrays in `LONGTEXT`. This limits local retrieval to cases
  where the full embedding set fits in PHP memory. The practical MVP limit is ~10K chunks at
  text-embedding-3-small (1536 floats = ~6KB each = ~60MB for 10K chunks). Sites with larger
  corpora MUST use Pinecone mode.
- ANN index decision (CFI-RAG-003): Neither SQLite VSS nor HNSW PHP are viable for standard WP
  hosting (no SQLite PHP extension guarantee, no reliable HNSW PHP library). The progressive
  approach (local + Pinecone) is the correct trade-off: simple, reliable, no new dependencies.
  Document the 10K chunk practical limit clearly.
- The `Embedding_Interface` is separate from `Provider_Interface` by design (different method
  signatures: `embed()` vs `chat()`), but shares the same registry registration pattern with a
  capability flag `supports_embedding`.
- Context assembly follows AXI's security-policy-first pattern verbatim -- this is the highest-scored
  RAG capability (4.65) and is adopted as-is. No MXC or BAA modifications to this component.

### Potential Challenges

- **Cron reliability on shared hosting**: `wp-cron` is triggered by page loads and may not fire
  reliably on low-traffic sites. Queue workers must be idempotent and tolerate missed ticks.
- **Embedding API costs**: Embedding generation is billed per token. Large re-index operations can
  be expensive. Workers must track status to avoid re-embedding already-processed chunks.
- **Chunk boundary context loss**: Overlap-based chunking (AXI winner) preserves context at
  boundaries at the cost of storage. Overlap defaults to ~10-15% of chunk size.

### Relevant Considerations

- **Anchor-first extraction** (Lesson Learned #1): Every interface definition cites concrete source
  anchors for Phase 06 implementors
- **Explicit N/A policy** (Lesson Learned #5): BAA has no chunking or embedding capability --
  documented as `N/A (no matching implementation)` throughout

### Behavioral Quality Focus

Checklist active: No (session produces contract documents, not application code)
BQC requirements embedded in contracts:
- Queue worker includes dead-letter handling, max attempt enforcement, stall recovery
- Retriever includes empty-result behavior, provider mismatch fallback, Pinecone error handling
- Context assembler includes token budget overflow truncation strategy

---

## 9. Testing Strategy

### Contract Validation

- Cross-reference all 4 CFI-RAG items against deliverables to confirm resolution
- Trace complete ingestion path: source adapter extracts text -> queue item created -> worker
  chunks -> embedding generated -> stored in `wp_wpaic_chunks`
- Trace complete retrieval path: user query -> embedding generated -> cosine similarity over
  `wp_wpaic_chunks` -> top-K selected -> context assembled -> prompt delivered to provider

### Completeness Checks

- Every source adapter method has: signature, parameter types, return type, error behavior
- Every queue state transition is covered (pending -> processing -> completed/failed/stalled)
- Every retrieval mode handles: empty result set, provider mismatch, Pinecone API error

### Cross-Reference

- CFI-RAG-001: Unified ingestion -> `content_source_adapter_interface.md` + `ingestion_queue_spec.md`
- CFI-RAG-002: Unified chunking -> `chunking_module_interface.md`
- CFI-RAG-003: ANN decision -> `retrieval_ranking_contract.md` (Section 5: ANN Decision)
- CFI-RAG-004: Embedding providers -> `embedding_provider_interface.md`

### Edge Cases

- Source adapter returns empty content (post has no extractable text)
- Embedding API returns error mid-batch (partial batch handling)
- Pinecone namespace mismatch between bot configurations
- Chunk count exceeds local retrieval practical limit (10K) with local mode configured
- Context token budget exceeded by retrieval results (truncation strategy)
- Ingestion queue stall: worker crashes after `status='processing'` set

---

## 10. Dependencies

### External Libraries

- None mandatory. Pinecone API is an optional external service.

### Reference Anchors

#### Ingestion Pipeline

- `axiachat-ai/includes/contexto-create.php` -- Content ingestion and chunking (primary AXI pattern)
- `axiachat-ai/includes/contexto-functions.php` -- Retrieval, context assembly, embedding generation
- `axiachat-ai/includes/contexto-modify.php` -- Index updates (delete-then-insert pattern)
- `mxchat-basic/admin/class-knowledge-manager.php` -- Queue infrastructure (primary MXC pattern), 7286 lines
- `mxchat-basic/includes/class-mxchat-chunker.php` -- Chunking strategies, configurable size, metadata
- `baachal/includes/content-indexer.php` -- N/A (no chunking/embedding; keyword search baseline only)

#### Admin Surface

- `axiachat-ai/includes/class-aichat-ajax.php` -- Context operations AJAX registration
- `mxchat-basic/js/knowledge-processing.js` -- Queue processing UI, progress tracking
- `baachal/admin/tabs/content.php` -- Content config, index stats (simple baseline reference)
- `baachal/admin/tabs/search.php` -- Search config, cache management (simple baseline reference)

### Other Sessions

- **Depends on**: `phase02-session01` (module map), `phase02-session02` (table schemas),
  `phase02-session03` (provider interface pattern, registry dispatch)
- **Depended by**: `phase02-session04` (runtime -- RAG context injection hook points),
  `phase02-session06` (security -- security policy configuration, audit logging),
  `phase02-session07` (migration -- knowledge base migration from source plugins)

---

## Next Steps

Session complete. Run `/validate` to verify session completeness.
