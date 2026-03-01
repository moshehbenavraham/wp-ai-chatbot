# AxiaChat Migration Strategy

**Session ID**: `phase02-session07-migration-strategy-extension-points-and-phase-handoff`
**Status**: Complete
**Created**: 2026-03-01

---

## 1. Overview

AxiaChat is the highest-complexity migration scenario:
- Uses individual option keys (good -- no blob decomposition needed)
- API keys may be encrypted (AXI v2+) or plaintext (older versions)
- Has per-bot configuration in `aichat_bots` table (no equivalent in MXC/BAA)
- Has context/embedding data in multiple tables (not migrated at MVP -- Phase 10)
- Has add-on configuration in option keys (deferred -- Phase 07+)

**Migration ID**: `axiachat_v1`
**Priority**: Required for sites migrating from AxiaChat. Settings-only migration is MVP scope;
full data migration (bots table, conversation history, context tables) is Phase 10 scope.

---

## 2. Option Key Mapping

### 2.1 Provider Settings

| Source Option | Source Type | Target Option | Transform | Notes |
|--------------|------------|--------------|-----------|-------|
| `aichat_openai_api_key` | string (may be AXI-encrypted) | `wpaic_provider_openai_key` | AXI-decrypt if needed, then WPAIC-encrypt | AXI uses same AES-256-GCM but different key derivation |
| `aichat_claude_api_key` | string (may be AXI-encrypted) | `wpaic_provider_claude_key` | AXI-decrypt if needed, then WPAIC-encrypt | |
| `aichat_gemini_api_key` | string (may be AXI-encrypted) | `wpaic_provider_gemini_key` | AXI-decrypt if needed, then WPAIC-encrypt | |
| `aichat_ai_provider` | string enum | `wpaic_provider_default` | `map_value(['openai'=>'openai','claude'=>'claude','gemini'=>'gemini'])` | |
| `aichat_default_model` | string | `wpaic_provider_default_model` | `to_string()` | Model slug may be deprecated; model registry handles |
| `aichat_timeout` | int | `wpaic_provider_timeout` | `to_int(5, 120)` | |
| `aichat_max_retries` | int | `wpaic_provider_max_retries` | `to_int(0, 5)` | |

### 2.2 Runtime Settings

| Source Option | Source Type | Target Option | Transform |
|--------------|------------|--------------|-----------|
| `aichat_streaming_enabled` | bool | `wpaic_runtime_stream_enabled` | `to_bool()` |
| `aichat_max_tokens` | int | `wpaic_runtime_max_tokens` | `to_int(1, 128000)` |
| `aichat_temperature` | float | `wpaic_runtime_temperature` | `to_float(0.0, 2.0)` |
| `aichat_input_max_length` | int | `wpaic_runtime_input_max_length` | `to_int(1, 10000)` |
| `aichat_max_messages` | int | `wpaic_runtime_max_messages` | `to_int(1, 100)` |
| `aichat_history_persistence` | bool | `wpaic_runtime_history_persistence` | `to_bool()` |
| `aichat_datetime_injection_enabled` | bool | `wpaic_runtime_datetime_injection` | `to_bool()` |
| `aichat_inject_user_context_enabled` | bool | `wpaic_runtime_user_context_injection` | `to_bool()` |
| `aichat_context_max_length` | int | `wpaic_runtime_context_max_length` | `to_int(100, 50000)` |
| `aichat_context_limit` | int | `wpaic_runtime_context_limit` | `to_int(1, 20)` |

### 2.3 Security Settings

| Source Option | Source Type | Target Option | Transform |
|--------------|------------|--------------|-----------|
| `aichat_honeypot_enabled` | bool | `wpaic_moderation_honeypot_enabled` | `to_bool()` |
| `aichat_rate_limit_enabled` | bool | `wpaic_moderation_rate_limit_enabled` | `to_bool()` |
| `aichat_rate_limit_max` | int | `wpaic_security_rate_limit_requests` | `to_int(1, 200)` |
| `aichat_rate_limit_window` | int | `wpaic_security_rate_limit_window` | `to_int(10, 3600)` |
| `aichat_banned_words` | string (newline-sep) | `wpaic_security_banned_words` | `to_string()` |
| `aichat_banned_word_message` | string | `wpaic_security_banned_word_message` | `to_string()` |
| `aichat_daily_message_limit` | int | `wpaic_privacy_daily_limit` | `to_int(0, PHP_INT_MAX)` |
| `aichat_ip_privacy_mode` | string | `wpaic_privacy_ip_mode` | `map_value(['anonymize'=>'hash','full'=>'raw','none'=>'none'])` |

### 2.4 RAG Settings

| Source Option | Source Type | Target Option | Transform |
|--------------|------------|--------------|-----------|
| `aichat_context_mode` | string enum | `wpaic_rag_context_mode` | `map_value(['embeddings'=>'embeddings','page'=>'page','manual'=>'manual'])` |
| `aichat_chunk_size` | int | `wpaic_rag_chunk_size` | `to_int(100, 10000)` |
| `aichat_chunk_overlap` | int | `wpaic_rag_chunk_overlap` | `to_int(0, 500)` |
| `aichat_retrieval_top_k` | int | `wpaic_rag_retrieval_top_k` | `to_int(1, 20)` |
| `aichat_retrieval_threshold` | float | `wpaic_rag_retrieval_threshold` | `to_float(0.0, 1.0)` |

---

## 3. AXI Encryption Compatibility

AxiaChat uses AES-256-GCM with the same wire format (base64 JSON envelope with `{v, m, iv, ct, tag}`).
However, it derives the key from `wp_salt('auth')` directly without the `WPAIC_MASTER_KEY` priority.

**Decryption strategy during migration**:
1. Read raw option value
2. Check `Encryption::is_encrypted($value)` (WPAIC detector also recognizes AXI format -- same schema)
3. If encrypted: attempt decrypt with WPAIC key derivation (which uses `wp_salt('auth')` as fallback)
4. If decrypt succeeds: value is now plaintext, re-encrypt with WPAIC key
5. If decrypt fails: value was encrypted with a different key; flag as `needs_manual` in report

This works on most sites (both use `wp_salt('auth')` unless `WPAIC_MASTER_KEY` is set).

---

## 4. Per-Bot Table Data (Phase 10 Scope)

AxiaChat's `aichat_bots` table contains per-bot configuration that maps to WPAIC's
`wp_wpaic_bots` table. This migration is Phase 10 scope (beyond MVP settings migration):

| AXI Column | WPAIC Column | Transform |
|-----------|-------------|-----------|
| `name` | `name` | identity |
| `system_prompt` | `system_prompt` | identity |
| `provider` | `provider` | identity |
| `model` | `default_model` | identity |
| `temperature` | `temperature` | identity |
| `max_tokens` | `max_tokens` | identity |
| `context_mode` | `context_mode` | identity |

**Note**: AXI bot IDs are integers; WPAIC uses UUIDs. A mapping table
`aichat_bot_id -> wpaic_bot_uuid` is needed for conversation history migration.

---

## 5. Source Anchors

- `axiachat-ai/axiachat-ai.php` -- activation hooks, option keys at plugin bootstrap
- `axiachat-ai/includes/model-registry.php` -- model config (source for model slug mapping)
- `axiachat-ai/includes/settings/encrypt-decrypt.php` -- encryption format (compatible with WPAIC)
- `axiachat-ai/includes/settings.php` -- full option key inventory (source of truth)
