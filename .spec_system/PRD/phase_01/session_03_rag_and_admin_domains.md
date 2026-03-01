# Session 03: RAG and Admin Domains

**Session ID**: `phase01-session03-rag-and-admin-domains`
**Status**: Not Started
**Estimated Tasks**: ~18
**Estimated Duration**: 2-4 hours

---

## Objective

Extract, score, and select winners for the RAG (DOM-RAG) and Admin (DOM-ADMIN) domains -- the knowledge pipeline and operational control surface that are central to the plugin's value proposition.

---

## Scope

### In Scope (MVP)
- Assemble RAG domain context packs from all 3 sources
- Extract RAG feature units (ingestion, chunking, indexing, retrieval, context assembly, embeddings)
- Score RAG capabilities across 5 dimensions
- Record RAG domain decisions
- Assemble admin domain context packs from all 3 sources
- Extract admin feature units (settings pages, option registration, diagnostics, operational controls)
- Score admin capabilities across 5 dimensions
- Record admin domain decisions

### Out of Scope
- RAG architecture or storage implementation decisions (Phase 02+)
- Admin UI wireframes or layout decisions
- Domains other than DOM-RAG and DOM-ADMIN

---

## Prerequisites

- [ ] Sessions 01-02 completed (matrix operational, foundational domains scored)
- [ ] Pattern established for context pack assembly and scoring workflow

---

## Reference Anchors

### RAG
- `ANC-RAG-AXI-001`: `axiachat-ai/includes/contexto-functions.php`, `axiachat-ai/includes/contexto-create.php`, `axiachat-ai/includes/contexto-modify.php`, `axiachat-ai/includes/add-ons/web-scraper/scraper-core.php`
- `ANC-RAG-MXC-001`: `mxchat-basic/admin/class-knowledge-manager.php`, `mxchat-basic/includes/class-mxchat-chunker.php`, `mxchat-basic/js/knowledge-processing.js`
- `ANC-RAG-BAA-001`: `baachal/includes/content-indexer.php`, `baachal/admin/tabs/content.php`, `baachal/admin/tabs/search.php`

### Admin
- `ANC-ADM-AXI-001`: `axiachat-ai/includes/settings.php`, `axiachat-ai/assets/js/settings.js`
- `ANC-ADM-MXC-001`: `mxchat-basic/includes/class-mxchat-admin.php`, `mxchat-basic/js/mxchat-admin.js`
- `ANC-ADM-BAA-001`: `baachal/admin/settings-page.php`, `baachal/admin/tabs/advanced.php`, `baachal/assets/admin.js`

---

## Deliverables

1. RAG domain context pack (bounded excerpts from 3 sources)
2. RAG domain feature extraction entries
3. RAG domain scored comparison and decision log
4. Admin domain context pack (bounded excerpts from 3 sources)
5. Admin domain feature extraction entries
6. Admin domain scored comparison and decision log

---

## Success Criteria

- [ ] RAG domain has scored entries for all 3 sources across 5 dimensions
- [ ] RAG winner selected with documented rationale (highest complexity domain)
- [ ] Admin domain has scored entries for all 3 sources across 5 dimensions
- [ ] Admin winner selected with documented rationale
- [ ] RAG-admin interaction points noted (admin controls for knowledge pipeline)
