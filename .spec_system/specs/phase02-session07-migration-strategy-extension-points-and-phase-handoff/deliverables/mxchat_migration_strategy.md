# MxChat Migration Strategy

**Session ID**: `phase02-session07-migration-strategy-extension-points-and-phase-handoff`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Overview

MxChat uses a monolithic `mxchat_options` array blob stored as a single option key. All settings
are accessed as `get_option('mxchat_options')['key']`. This requires blob decomposition via
`define_from_blob()` for every migrated field. API keys are always plaintext (no encryption) --
all must be encrypted during migration.

**Migration ID**: `mxchat_v1`
**Primary challenge**: Blob decomposition (single option -> 30+ individual keys)
**Encryption needed**: All API keys (currently plaintext)

---

## 2. Blob Decomposition Map

All migrations use `define_from_blob('mxchat_options', $sub_key, $target_key, $transform)`.

### 2.1 Provider Settings

| Sub-Key in Blob | Target Option | Transform | Encrypt | Notes |
|----------------|--------------|-----------|---------|-------|
| `api_key` | `wpaic_provider_openai_key` | `to_string()` | YES | OpenAI is MXC default |
| `xai_api_key` | `wpaic_provider_grok_key` | `to_string()` | YES | |
| `voyage_api_key` | `wpaic_provider_voyage_key` | `to_string()` | YES | |
| `selected_model` | `wpaic_provider_default_model` | `to_string()` | no | |
| `ai_provider` | `wpaic_provider_default` | `map_value(['openai'=>'openai','xai'=>'grok','anthropic'=>'claude'])` | no | |

### 2.2 Runtime Settings

| Sub-Key in Blob | Target Option | Transform |
|----------------|--------------|-----------|
| `enable_streaming_toggle` | `wpaic_runtime_stream_enabled` | `to_bool()` |
| `max_tokens` | `wpaic_runtime_max_tokens` | `to_int(1, 128000)` |
| `temperature` | `wpaic_runtime_temperature` | `to_float(0.0, 2.0)` |
| `max_history_messages` | `wpaic_runtime_max_messages` | `to_int(1, 100)` |
| `chat_history_enabled` | `wpaic_runtime_history_persistence` | `to_bool()` |

### 2.3 RAG / Knowledge Settings

| Sub-Key in Blob | Target Option | Transform |
|----------------|--------------|-----------|
| `knowledge_enabled` | `wpaic_rag_context_mode` | `fn($v) => $v ? 'embeddings' : 'none'` |
| `chunk_size` | `wpaic_rag_chunk_size` | `to_int(100, 10000)` |
| `top_k_results` | `wpaic_rag_retrieval_top_k` | `to_int(1, 20)` |
| `pinecone_api_key` | `wpaic_rag_pinecone_key` | `to_string()` + encrypt |
| `pinecone_environment` | `wpaic_rag_pinecone_endpoint` | custom transform (build URL from env) |

### 2.4 Security Settings

| Sub-Key in Blob | Target Option | Transform |
|----------------|--------------|-----------|
| `rate_limit_enabled` | `wpaic_moderation_rate_limit_enabled` | `to_bool()` |
| `rate_limit_count` | `wpaic_security_rate_limit_requests` | `to_int(1, 200)` |
| `daily_message_limit` | `wpaic_privacy_daily_limit` | `to_int(0, PHP_INT_MAX)` |
| `honeypot_enabled` | `wpaic_moderation_honeypot_enabled` | `to_bool()` |
| `banned_words` | `wpaic_security_banned_words` | `to_string()` |

---

## 3. Standalone MxChat Options (non-blob)

Some MxChat settings are NOT in the blob:

| Source Option | Target Option | Transform |
|--------------|--------------|-----------|
| `mxchat_widget_title` | `wpaic_widget_title` | `to_string()` |
| `mxchat_welcome_message` | `wpaic_widget_welcome_message` | `to_string()` |
| `mxchat_pinecone_addon_options` | see pinecone keys above | blob decompose |

---

## 4. Data Migration (Phase 10)

MxChat does not have a custom sessions or messages table. Conversation history is NOT migrated
(no persistent storage in MxChat MVP; users start fresh). No table data migration needed.

MxChat knowledge base (embeddings) uses Pinecone exclusively. Pinecone data is accessed via
API key (migrated above) -- no local data migration needed.

---

## 5. Compatibility Notes

- MxChat multi-bot configuration (`mxchat_bots` if present) -- map to `wp_wpaic_bots` in
  Phase 10. Multi-bot is not a standard MxChat basic feature (addon only).
- MxChat uses jQuery admin JS. No admin JS migration -- WPAIC uses vanilla JS (fresh start).
- MxChat addon options (e.g., `mxchat_pinecone_addon_options`) are stored as separate blobs.
  These are migrated as additional blob decompositions in the same migration pass.

---

## 6. Source Anchors

- `mxchat-basic/mxchat-basic.php` -- option registration, activation hooks
- `mxchat-basic/includes/class-mxchat-admin.php` -- full blob option key inventory
- `mxchat-basic/admin/class-knowledge-manager.php` -- Pinecone options structure
