# Task Checklist

**Session ID**: `phase02-session05-rag-pipeline-knowledge-base-and-embedding-contracts`
**Total Tasks**: 19
**Estimated Duration**: 3-4 hours
**Created**: 2026-03-01

---

## Legend

- `[x]` = Completed
- `[ ]` = Pending
- `[P]` = Parallelizable (can run with other [P] tasks)
- `[S0205]` = Session reference (phase 02, session 05)
- `TNNN` = Task ID

---

## Progress Summary

| Category | Total | Done | Remaining |
|----------|-------|------|-----------|
| Setup | 2 | 2 | 0 |
| Foundation | 4 | 4 | 0 |
| Implementation | 9 | 9 | 0 |
| Testing | 4 | 4 | 0 |
| **Total** | **19** | **19** | **0** |

---

## Setup (2 tasks)

Initial verification and directory preparation.

- [x] T001 [S0205] Verify prerequisites met: confirm sessions 01-04 deliverables accessible, confirm
  `wp_wpaic_knowledge`, `wp_wpaic_chunks`, `wp_wpaic_queue` schemas in session 02
  `database_table_schemas.md`, confirm `Provider_Interface` and registry pattern in session 03
  `provider_interface_contract.md`, confirm RAG module paths in session 01 `directory_structure.md`,
  review CFI-RAG-001 through CFI-RAG-004 from Phase 01 carry-forward register
- [x] T002 [S0205] Create deliverable directory structure under
  `.spec_system/specs/phase02-session05-rag-pipeline-knowledge-base-and-embedding-contracts/deliverables/`

---

## Foundation (4 tasks)

Architectural decisions and structural definitions that downstream tasks depend on.

- [x] T003 [S0205] [P] Define content source adapter registration model -- decide adapter registry vs
  hook-based registration; enumerate MVP sources (wp_posts, wp_pages, wc_products, manual_text,
  url, sitemap, pdf); define lifecycle hooks (`wpaic_register_rag_sources`,
  `wpaic_rag_source_extract`); document RES-XD-007 boundary (WooCommerce enrichment deferred)
  (ref: `axiachat-ai/includes/contexto-create.php` content extraction patterns,
  `mxchat-basic/admin/class-knowledge-manager.php` source type dispatch)
- [x] T004 [S0205] [P] Resolve CFI-RAG-003 ANN index decision -- evaluate SQLite VSS (requires PHP
  ext, not standard on shared hosting), HNSW in PHP (no stable library), Pinecone (external SaaS),
  brute-force cosine (simple, bounded); document the progressive design decision: local cosine for
  MVP bounded at ~10K chunks, Pinecone for production-scale; define the 10K chunk practical memory
  limit calculation (1536-dim float array = ~6KB, 10K chunks = ~60MB PHP heap); no new dependencies
  required for MVP (ref: `axiachat-ai/includes/contexto-functions.php` local cosine retrieval,
  `mxchat-basic/admin/class-knowledge-manager.php` Pinecone integration)
- [x] T005 [S0205] [P] Define embedding provider interface shape -- decide `Embedding_Interface` vs
  capability flag on `Provider_Interface`; choose separate interface (different method: `embed()` vs
  `chat()`); define registration via `wpaic_register_providers` with `supports_embedding` capability
  flag; adapter location `includes/provider/adapters/`; RES-XD-008 alignment (embedding providers
  through same registry as chat providers)
  (ref: `axiachat-ai/includes/contexto-functions.php` `generate_embedding()` function,
  `mxchat-basic/admin/class-knowledge-manager.php` multi-provider embedding dispatch)
- [x] T006 [S0205] [P] Define context assembly security policy model -- confirm always-on security
  policy injection (AXI pattern, DEC-RAG-004 adopt-as-is); define policy position in prompt
  (always first, before any user/context content); define template token allowlist mechanism
  (registry of safe functions, no eval); define LINK marker format `[LINK:post_id]` for safe URL
  injection; define CONTEXT block numbering format `[CONTEXT 1]`, `[CONTEXT 2]`
  (ref: `axiachat-ai/includes/contexto-functions.php` full context assembly pipeline)

---

## Implementation (9 tasks)

Contract document authoring -- each task produces a deliverable.

- [x] T007 [S0205] Write content source adapter interface specification
  (`deliverables/content_source_adapter_interface.md`) -- define `Source_Adapter_Interface` with
  methods `get_source_id()`, `get_label()`, `get_supported_item_types()`, `discover_items()`,
  `extract_content($item_id, $item_data)`, `get_item_metadata($item_id)`; define item type constants
  (`wp_post`, `wp_page`, `wc_product`, `manual_text`, `url`, `pdf`); registration hook
  `wpaic_register_rag_sources`; adapter compliance rules; file/class naming conventions
  (ref: `axiachat-ai/includes/contexto-create.php` per-type content extraction,
  `mxchat-basic/admin/class-knowledge-manager.php` lines 1-200 source dispatch patterns)
- [x] T008 [S0205] Write ingestion queue specification (`deliverables/ingestion_queue_spec.md`) --
  queue item lifecycle (pending -> processing -> completed/failed/stalled), enqueue API
  `WPAIC\RAG\Queue::enqueue($adapter_id, $item_type, $item_data, $knowledge_id, $priority)`,
  cron worker design (`wpaic_rag_process_queue` scheduled event, 3-minute interval, batch size 5),
  stall detection (>10min in processing state, reset to pending if under max_attempts), retry policy
  (exponential backoff: 2^attempts minutes), status update hooks, admin polling contract
  (ref: `mxchat-basic/admin/class-knowledge-manager.php` queue table operations,
  `axiachat-ai/includes/contexto-functions.php` N/A -- no queue; `baachal/includes/content-indexer.php`
  N/A -- synchronous only)
- [x] T009 [S0205] Write chunking module interface (`deliverables/chunking_module_interface.md`) --
  resolves CFI-RAG-002; define `Chunker::chunk($text, $options)` signature; options: `chunk_size`
  (words, default 1000, range 200-5000), `overlap_size` (words, default 150, range 0-500),
  deterministic chunk ID format `{knowledge_id}#{post_id}#{chunk_index}`; output format per chunk:
  `{chunk_id, chunk_index, content, token_count, overlap_start, overlap_end}`; content-type
  pre-processing hooks (`wpaic_rag_pre_chunk`); empty content handling; metadata extraction
  (ref: `axiachat-ai/includes/contexto-functions.php` overlap-based chunking 1000/180 words,
  `mxchat-basic/includes/class-mxchat-chunker.php` 3-tier splitting, configurable chunk size)
- [x] T010 [S0205] Write embedding provider interface (`deliverables/embedding_provider_interface.md`) --
  resolves CFI-RAG-004; define `Embedding_Interface` in namespace `WPAIC\Provider`; method
  `embed($texts, $model, $params)` for batch embedding; method `get_embedding_id()` -- unique slug;
  method `get_supported_dimensions()` -- returns array of valid dimensions; integration with
  `Provider_Registry` via `supports_embedding` flag; adapter compliance rules (ECR-01--ECR-05);
  batch size limits per provider (OpenAI: 2048 inputs, Voyage AI: 128 inputs, Gemini: 1 input);
  dimension normalization contract for cross-provider compatibility; error handling (null on failure,
  never throw); file/class naming: `class-{provider}-embedding-adapter.php`
  (ref: `axiachat-ai/includes/contexto-functions.php` `generate_embedding()` OpenAI+Gemini,
  `mxchat-basic/admin/class-knowledge-manager.php` Voyage AI + multi-provider dispatch)
- [x] T011 [S0205] Write retrieval and ranking contract (`deliverables/retrieval_ranking_contract.md`) --
  resolves CFI-RAG-003; define `Retriever` class interface: `retrieve($query_embedding, $knowledge_id,
  $mode, $top_k, $params)`; four retrieval modes: `local` (PHP cosine similarity over
  `wp_wpaic_chunks`), `pinecone` (Pinecone API upsert/query), `page` (current-page post ID lookup),
  `auto` (resolved from `wp_wpaic_knowledge.context_type`); cosine similarity function signature;
  Pinecone endpoint security (HTTPS-only, `wpaic_pinecone_allowed_domains` filter, allowlist default
  `api.pinecone.io`); result format per chunk: `{chunk_id, content, title, source_url, score,
  post_id}`; ANN scalability decision (Section 5): local bounded at 10K chunks, Pinecone for
  production, no ANN library required for MVP; top-K default 5, max 20; similarity threshold filter
  (configurable, default 0.70)
  (ref: `axiachat-ai/includes/contexto-functions.php` full retrieval pipeline all 4 modes,
  `mxchat-basic/admin/class-knowledge-manager.php` Pinecone integration, bot-specific namespaces)
- [x] T012 [S0205] Write context assembly specification (`deliverables/context_assembly_spec.md`) --
  adopts DEC-RAG-004 (AXI context assembly, 4.65 score, adopt-as-is); define prompt structure:
  (1) Security policy block (always first, never omitted), (2) datetime awareness string,
  (3) system instructions with template tokens, (4) numbered CONTEXT blocks from retrieval results,
  (5) QUESTION block; template token allowlist registry (hook `wpaic_instruction_tokens_map`);
  LINK marker system (`[LINK:post_id]` in response -> converted to anchor on post-process);
  token budget management: context window - system_tokens - history_tokens = available_context_tokens;
  truncation strategy when budget exceeded (drop lowest-scored chunks first); suggestion generation
  hook `wpaic_rag_suggestions`; `wpaic_system_prompt`, `wpaic_security_policy` filter hooks
  (ref: `axiachat-ai/includes/contexto-functions.php` full context assembly, security policy,
  template tokens, link markers; `mxchat-basic/admin/class-knowledge-manager.php` N/A -- context
  assembly embedded in integrator class; `baachal/includes/content-indexer.php` N/A)
- [x] T013 [S0205] Write knowledge admin contract (`deliverables/knowledge_admin_contract.md`) --
  REST endpoints under `/wp-json/wpaic/v1/knowledge/`: LIST (GET, admin-only), CREATE (POST),
  GET (GET), UPDATE (PUT), DELETE (DELETE), REINDEX (POST /:id/reindex), QUEUE_STATUS (GET
  /:id/queue), TEST_SEARCH (POST /:id/search); each endpoint: route, method, auth requirement
  (`manage_options` capability), nonce strategy (`wpaic_knowledge_nonce`), request schema, response
  schema, error codes; queue dashboard polling contract (3-second interval, queue status endpoint);
  bulk operations (reindex-all, clear-failed); source management drawer fields per source type
  (ref: `mxchat-basic/js/knowledge-processing.js` queue UI patterns, progress tracking;
  `axiachat-ai/includes/class-aichat-ajax.php` context AJAX patterns;
  `baachal/admin/tabs/content.php` simple stats reference; `baachal/admin/tabs/search.php`
  test search reference)
- [x] T014 [S0205] Write auto-sync and post lifecycle hooks section -- extend
  `deliverables/ingestion_queue_spec.md` with auto-sync design: `save_post` hook integration,
  configurable auto-sync modes (`updates` only vs `updates_and_new`), per-post exclusion via
  meta `_wpaic_exclude_from_index`; delete hook for chunk cleanup (`delete_post` ->
  `DELETE FROM wp_wpaic_chunks WHERE post_id = ?`); WP-Cron scheduling contract for queue worker
  (ref: `mxchat-basic/admin/class-knowledge-manager.php` auto-sync patterns,
  `baachal/includes/content-indexer.php` `save_post` hook)
- [x] T015 [S0205] Cross-reference all contracts and verify CFI-RAG item resolution -- trace
  CFI-RAG-001 (ingestion) to `content_source_adapter_interface.md` + `ingestion_queue_spec.md`;
  CFI-RAG-002 (chunking) to `chunking_module_interface.md`; CFI-RAG-003 (ANN decision) to
  `retrieval_ranking_contract.md` Section 5; CFI-RAG-004 (embedding providers) to
  `embedding_provider_interface.md`; verify session 02 table schemas referenced in each deliverable;
  verify session 03 provider registry pattern referenced in embedding interface; add resolution
  table to `implementation-notes.md`

---

## Testing (4 tasks)

Verification and quality assurance of contract documents.

- [x] T016 [S0205] Validate pipeline end-to-end traceability -- trace complete ingest path from
  source adapter `extract_content()` through queue enqueue, cron worker, `Chunker::chunk()`,
  `Embedding_Interface::embed()`, to `wp_wpaic_chunks` insert; confirm no gaps or undefined
  intermediate states; confirm stall detection covers all failure modes
- [x] T017 [S0205] Validate retrieval path end-to-end -- trace query from runtime context injection
  hook through `Embedding_Client` embed, `Retriever::retrieve()` in local mode and Pinecone mode,
  `Context_Assembler` prompt construction, to final prompt passed to `Provider_Interface::chat()`;
  verify token budget calculation works at boundary conditions (zero chunks, max chunks)
- [x] T018 [S0205] Verify ASCII encoding and Unix LF line endings on all deliverable files
- [x] T019 [S0205] Write implementation notes (`implementation-notes.md`) -- session summary, key
  architectural decisions (ANN decision rationale, Embedding_Interface separation rationale,
  progressive local->Pinecone path), CFI-RAG resolution table, lessons learned, recommendations
  for Phase 06 implementors

---

## Completion Checklist

Before marking session complete:

- [x] All tasks marked `[x]`
- [x] All 7 deliverable documents written
- [x] CFI-RAG-001 through CFI-RAG-004 resolved in contracts
- [x] ANN scalability decision documented with rationale
- [x] All files ASCII-encoded
- [x] Unix LF line endings
- [x] `implementation-notes.md` updated with CFI resolution table
- [x] Every deliverable cites reference anchors from >= 2 source plugins
- [x] Ready for `/validate`

---

## Next Steps

Run `/validate` to verify session completeness.
