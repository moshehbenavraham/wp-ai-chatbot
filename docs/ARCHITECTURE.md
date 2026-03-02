# Architecture

## System Overview

WP AI Chatbot is a WordPress plugin providing provider-agnostic AI chatbot capabilities with RAG knowledge retrieval, tool/function calling, and a full admin operations surface. The plugin combines patterns from three reference implementations (axiachat-ai, mxchat-basic, baachal) into a unified, production-grade codebase.

## Component Map

```
[WordPress Core]
     |
     v
[Plugin Bootstrap] wp-ai-chatbot.php
     |  (constants, version guards, autoloader, lifecycle hooks)
     |
     +-- [Provider Layer]        includes/provider/
     |     +-- Provider_Registry (singleton, lazy init, instance cache)
     |     +-- Model_Registry    (25+ models, deprecation chains)
     |     +-- Adapters: OpenAI, Claude, Gemini
     |     \-- --> [AI Provider APIs]
     |
     +-- [Chat Runtime]          includes/runtime/
     |     +-- Message_Pipeline  (4-stage: validate, build, dispatch, respond)
     |     +-- Session_Manager   (UUID v4 tokens, rolling TTL, ownership)
     |     +-- Output_Formatter  (markdown->HTML, wp_kses, suggestions)
     |     \-- Macro_Router      (intent routing)
     |
     +-- [RAG Pipeline]          includes/rag/
     |     +-- Content_Source_Registry --> [Posts, PDF, Web sources]
     |     +-- Embedding_Registry      --> [OpenAI Embeddings]
     |     +-- Chunker          (paragraph->sentence->word, overlap)
     |     +-- Ingestion_Pipeline (batch, atomic claim, stall recovery)
     |     +-- Knowledge_Index   (upsert, cosine similarity search)
     |     \-- Retrieval_Engine  (query->embed->search->assemble)
     |
     +-- [Tools / MCP]           includes/tools/ + includes/mcp/
     |     +-- Tool_Registry     (native + webhook + MCP tools)
     |     +-- Tool_Executor     (permission model, audit trail)
     |     +-- Tool_Continuation (multi-turn loop, MAX_LOOPS=5)
     |     +-- Mcp_Client        (JSON-RPC 2.0, MCP 2025-06-18)
     |     \-- Webhook_Tool      (SSRF-protected, static params)
     |
     +-- [Security]              includes/security/
     |     +-- Encryption        (AES-256-GCM, versioned envelope)
     |     +-- Sanitizer         (12 TYPE_* constants, typed aliases)
     |     +-- Auth_Enforcer     (AJAX+REST dual-check, session ownership)
     |     +-- Content_Moderator (blocklist filter chain)
     |     +-- Privacy_Manager   (WP exporters/erasers, retention policy)
     |     \-- Audit_Logger      (15 EVENT_* constants, daily log files)
     |
     +-- [Admin]                 includes/admin/
     |     +-- Admin_Router      (9 submenu pages, dispatch)
     |     +-- Settings_Manager  (get/set with transparent encrypt/decrypt)
     |     +-- Admin_Page        (abstract base, tabs, ARIA landmarks)
     |     \-- Pages: General, Knowledge, Tools, Security, Diagnostics,
     |              Migration, Appearance, Transcripts, Chat
     |
     +-- [Migration]             includes/migration/
     |     +-- Migration_Registry (semver runner, transient lock)
     |     +-- Lifecycle_Manager  (activate/deactivate/uninstall, 7 tables)
     |     +-- Migration_Runner   (detect, dry-run, run, rollback, backup)
     |     \-- Migrators: Axiachat, Mxchat, Baachal
     |
     +-- [WooCommerce]           includes/woocommerce/
     |     \-- HPOS-aware, product context enrichment
     |
     +-- [Infrastructure]        lib/
     |     +-- health-endpoint.php   (WP REST health check)
     |     +-- rate-limiter.php      (IP-based, transient-backed)
     |     +-- backup-manager.php    (export/import JSON)
     |     \-- structured-logger.php (daily JSON log files)
     |
     +-- [Frontend]              assets/ + templates/
     |     +-- chatbot.js  (WPAICChat IIFE, SSE streaming, instance registry)
     |     +-- chatbot.css (CSS custom props, palettes, mobile sheet, a11y)
     |     \-- chatbot-widget.php (floating+inline modes, ARIA, GDPR, honeypot)
     |
     \-- [Admin Frontend]        admin/
           +-- admin.js  (WPAICAdmin: toast, modal, drawer, validation, autosave)
           \-- admin.css (design tokens, skeleton loaders, scroll reveal, responsive)
```

## Plugin Singleton

`includes/class-plugin.php` is the central orchestrator (~55KB). It:
- Initializes all subsystems on `wpaic_init` at priority 5-20
- Registers core providers, content sources, embedding providers, RAG, tools, and security
- Registers all REST routes (providers, sessions, knowledge, tools, transcripts, migration)
- Manages cron scheduling/cleanup and Google Fonts CDN loading
- Detects WooCommerce + HPOS support via `class_exists` guards

## Tech Stack

| Technology | Purpose | Rationale |
|------------|---------|-----------|
| PHP >=7.4 | Plugin runtime | WordPress standard; broad host compatibility |
| Vanilla JS (IIFE) | Frontend + admin | No build step; simpler deployment and review |
| CSS Custom Properties | Theming | Runtime palette switching without rebuild |
| WP REST API | All endpoints | Native WordPress; consistent auth model |
| WP Transients | Rate limiting, caching | Built-in; works across hosting environments |
| AES-256-GCM (OpenSSL) | API key encryption | Strong AEAD; versioned envelope format |
| Docker php:8.3-cli | Local PHP tooling | PHP not installed locally; consistent environment |
| smalot/pdfparser | PDF text extraction | Pure PHP; no external service dependency |

## Data Layer

### Custom Tables (7, created via dbDelta on activation)

| Table | Purpose |
|-------|---------|
| `wpaic_sessions` | Chat sessions (token, user_id/ip_hash, TTL, aggregates) |
| `wpaic_messages` | Message history (role, content, provider, model, cost_micros) |
| `wpaic_chunks` | Knowledge base chunks (post_id, type, content, embedding vector) |
| `wpaic_leads` | Lead capture data |
| `wpaic_usage_daily` | Aggregated daily usage statistics |
| `wpaic_tool_calls` | Tool execution history and audit trail |
| `wpaic_knowledge_status` | Ingestion pipeline status tracking |

### Options API

- Plugin settings (`wpaic_*` options, ~40+ keys)
- Sensitive fields transparently encrypted via `Settings_Manager`
- Webhook tools stored as `wpaic_webhook_tools` (JSON array)
- MCP servers stored as `wpaic_mcp_servers` (JSON array)
- Migration backups as `wpaic_migration_backup_{source}_{ts}` (autoload=false)

### Migration Strategy

- **Internal upgrades**: `Migration_Registry` runs semver-ordered migrations with transient lock
- **From source plugins**: `Migration_Runner` with detect, dry-run, live run (with backup), and rollback
- Migrators for axiachat-ai (6 mappings), mxchat-basic (10 mappings), baachal (13 mappings)

## Data Flow

### Chat Request

```
User Input --> chatbot.js (sanitize, session token)
  --> wp_ajax_wpaic_chat (Message_Pipeline::handle_ajax)
    --> Auth_Enforcer::check_ajax (nonce + session ownership)
    --> Content_Moderator::check (blocklist filter)
    --> Rate Limiter check
    --> Retrieval_Engine (embed query -> cosine search -> assemble context)
    --> Provider_Registry::get(provider)->chat(messages)
       [if tools enabled] --> Tool_Continuation::run (up to 5 loops)
    --> Output_Formatter::format (markdown->HTML, wp_kses, suggestions)
  --> SSE stream or JSON response
  --> chatbot.js (render, update session)
```

### RAG Ingestion

```
Admin triggers ingest --> Ingestion_Pipeline::enqueue(source_id, item_ids)
  --> wpaic_process_ingestion cron (every 5 min)
    --> atomic claim (UPDATE + UUID4)
    --> Content_Source::get_content(item_id)
    --> Chunker::chunk(text, size, overlap)
    --> Embedding_Provider::embed_batch(chunks)
    --> Knowledge_Index::upsert_chunk() (ON DUPLICATE KEY)
```

## Key Decisions

- **No build step**: Vanilla JS/CSS deployed as-is; reduces contributor friction
- **Streaming auto-disabled with tools**: Tool continuation requires full response parsing; SSE resumes for non-tool requests
- **PHP cosine similarity**: Knowledge_Index::search() runs in PHP; suitable for small-medium knowledge bases; external vector DB is a future option
- **Tools opt-in**: `wpaic_tools_enabled` defaults to 0; must be explicitly enabled
- **MCP 2025-06-18**: JSON-RPC 2.0 over HTTP; no WebSocket/SSE transport yet

## Reference Source Analysis

Feature maps from the three source plugins used during planning:
- [axiachat-ai Feature Map](axiachat-ai-feature-map.md)
- [mxchat-basic Feature Map](mxchat-basic-feature-map.md)
- [baachal Feature Map](baachal-feature-map.md)
