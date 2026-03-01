# Session 05: RAG Pipeline, Knowledge Base, and Embedding Contracts

**Session ID**: `phase02-session05-rag-pipeline-knowledge-base-and-embedding-contracts`
**Status**: Not Started
**Estimated Tasks**: ~18
**Estimated Duration**: 2-4 hours

---

## Objective

Design the retrieval-augmented generation pipeline contracts including content source adapters, ingestion queue, chunking module, embedding providers, retrieval/ranking, and context assembly interfaces.

---

## Scope

### In Scope (MVP)
- Content source adapter interface (posts, pages, products, manual entries, web/PDF where scoped)
- Ingestion queue and worker design (DB-backed queue, retry logic, status tracking)
- Unified chunking module interface (merging AXI overlap-based and MXC chunker patterns)
- Embedding provider interface (separate from chat provider, pluggable backends)
- Retrieval modes and ranking contracts (4 modes from AXI, relevance scoring)
- Context assembly and security policy injection (what context reaches the prompt, trust boundaries)
- ANN index design considerations for scalability
- Knowledge base admin contracts (CRUD operations, status views, re-index triggers)

### Out of Scope
- WooCommerce product-specific retrieval enrichment (deferred, session 07 extension points)
- Web scraper implementation details (Phase 06)
- Actual embedding model selection (Phase 06)

---

## Prerequisites

- [ ] Session 01 completed (module map with RAG module location)
- [ ] Session 02 completed (knowledge chunks table, ingestion queue table schemas)
- [ ] Session 03 completed (provider interface pattern reusable for embedding providers)
- [ ] Phase 01 DOM-RAG winner summary reviewed (AXI 4/5, MXC ingestion queue)
- [ ] Carry-forward items reviewed: CFI-RAG-001 (unified ingestion), CFI-RAG-002 (unified chunking), CFI-RAG-003 (ANN index), CFI-RAG-004 (embedding providers)

---

## Deliverables

1. Content source adapter interface specification (registration, discovery, extraction contract)
2. Ingestion queue specification (queue model, worker lifecycle, retry policy, status tracking)
3. Chunking module interface (input/output format, overlap strategy, size constraints)
4. Embedding provider interface (request/response, batch support, model metadata)
5. Retrieval and ranking contract (query interface, scoring method, result format)
6. Context assembly specification (prompt construction rules, security policy injection, token budget)
7. Knowledge admin contract (CRUD endpoints, status reporting, bulk operations)

---

## Success Criteria

- [ ] Ingestion pipeline supports all Phase 01 in-scope content sources
- [ ] Chunking module merges best patterns from AXI and MXC without over-abstraction
- [ ] Embedding provider interface is pluggable (local, OpenAI, Pinecone-compatible)
- [ ] Context assembly enforces security policy (no leaked sensitive content in prompts)
- [ ] ANN index design is optional/progressive (works without ANN, improves with it)
- [ ] Carry-forward items CFI-RAG-001 through CFI-RAG-004 are fully addressed
