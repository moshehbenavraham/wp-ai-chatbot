# Provider Domain Context Pack

## Pack Metadata

| Field | Value |
|---|---|
| `pack_id` | `CP-P01-S01-001` |
| `session_id` | `phase01-session01-scoring-framework-and-provider-domain` |
| `phase` | `1` |
| `created_date` | `2026-03-01` |
| `author` | `implement-ai` |
| `context_budget_tokens` | `48000` |
| `domain_scope` | `["DOM-PROVIDER"]` |
| `source_plugins` | `["SRC-AXI", "SRC-MXC", "SRC-BAA"]` |

---

## Domain: `DOM-PROVIDER` - `provider`

**Slice Count**: 8 slices
**Sources Covered**: `SRC-AXI`, `SRC-MXC`, `SRC-BAA`

---

### Slice 1 - AxiaChat Provider Registry

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S01-001-PRO-01` |
| `anchor_id` | `ANC-PRO-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/class-aichat-provider-registry.php` |
| `symbol_or_scope` | `AIChat_Provider_Registry` |
| `excerpt_summary` | Singleton + Factory registry for provider adapters. Supports dynamic registration via `aichat_register_providers` hook, instance caching by provider ID + config hash, interface compliance validation (`AIChat_Provider_Interface`), provider enable/disable toggling, alias normalization (anthropic -> claude), and debug logging. 298 lines. |
| `excerpt_lines` | `L1-L298` |
| `token_estimate` | `3200` |
| `notes` | Pure orchestration layer; no direct API calls. All providers must implement `AIChat_Provider_Interface`. |

---

### Slice 2 - AxiaChat OpenAI Provider

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S01-001-PRO-02` |
| `anchor_id` | `ANC-PRO-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/providers/class-openai-provider.php` |
| `symbol_or_scope` | `AIChat_OpenAI_Provider` |
| `excerpt_summary` | OpenAI adapter with dual-API routing: GPT-5.x models use Responses API (`/v1/responses`), others use Chat Completions API (`/v1/chat/completions`). Multi-round tool calling (up to 5 rounds via `aichat_max_tool_rounds` filter). GPT-5 handshake pattern returns `tool_pending` with `response_id` for client-side execution. Agency proxy fallback for auth. Per-model cost calculation in microcents from pricing table. Leaked function_call token cleanup for GPT-5.x. 1177 lines. |
| `token_estimate` | `8500` |
| `secondary_domains` | `["DOM-SECURITY"]` |
| `notes` | Uses `AIChat_Tool_Execution` trait. Timeout: 45s (completions), 60s (responses). No built-in retry logic. |

---

### Slice 3 - AxiaChat Claude Provider

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S01-001-PRO-03` |
| `anchor_id` | `ANC-PRO-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/providers/class-claude-provider.php` |
| `symbol_or_scope` | `AIChat_Claude_Provider` |
| `excerpt_summary` | Anthropic Claude adapter with multi-round tool calling and handshake pattern. Round 1 returns `tool_pending` with tool_calls list; client executes tools and calls `continue_from_tool_pending()`. Tool state persisted to `wp_aichat_tool_states` DB table (one-time use, auto-cleanup). Fallback chain via `aichat_get_fallback_chain('anthropic')` with 4-tier models. Exponential backoff retry (3 attempts) for 429/503/529 errors. Web search via `aichat_claude_messages_tools` filter. 1200+ lines. |
| `token_estimate` | `8800` |
| `secondary_domains` | `["DOM-SECURITY"]` |
| `notes` | Uses `AIChat_Tool_Execution` trait. Timeout: 60s. Retry logic: exponential backoff. DB-backed tool state with `$wpdb->prepare()`. |

---

### Slice 4 - AxiaChat Gemini Provider

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S01-001-PRO-04` |
| `anchor_id` | `ANC-PRO-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/providers/class-gemini-provider.php` |
| `symbol_or_scope` | `AIChat_Gemini_Provider` |
| `excerpt_summary` | Google Gemini adapter with Gemini-native message format (`contents[]` with `parts[]`), separate `systemInstruction` field, `generationConfig` object. Thinking mode required for pro models (2.5-pro, 3-pro), disabled by default for flash. Streaming via `:streamGenerateContent` endpoint. Multi-tool support: native `googleSearch`/`codeExecution` combined with `functionDeclarations`. Pricing populated from centralized model registry. 1704 lines. |
| `token_estimate` | `9200` |
| `notes` | Uses `AIChat_Tool_Execution` trait. API base: `generativelanguage.googleapis.com/v1beta`. Default model: `gemini-2.5-flash`. |

---

### Slice 5 - AxiaChat Model Registry

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S01-001-PRO-05` |
| `anchor_id` | `ANC-PRO-AXI-001` |
| `source_id` | `SRC-AXI` |
| `source_path` | `axiachat-ai/includes/model-registry.php` |
| `symbol_or_scope` | `aichat_get_model_registry`, `aichat_resolve_model`, `aichat_get_default_model`, `aichat_get_fallback_chain` |
| `excerpt_summary` | Centralized model catalog (35+ models) across OpenAI, Anthropic, Gemini. Per-model metadata: id, provider, label, tags, context window, max output, recommended output, thinking support, multimodal, pricing (per 1M tokens), aliases, deprecated IDs, is_default, fallback_order. Model resolution: exact match -> alias/deprecated -> prefix match -> provider default. Fallback chains: Anthropic (4-tier), Gemini (3-tier). Legacy pricing bridge for backward compatibility. JS payload builder for `wp_localize_script`. 863 lines. |
| `token_estimate` | `5800` |
| `notes` | Filterable via `aichat_model_registry` hook. Static cache. Provides defaults: gpt-5.2-chat-latest (OpenAI), claude-sonnet-4-5 (Anthropic), gemini-2.5-flash (Gemini). |

---

### Slice 6 - MxChat Integrator (Provider Routing)

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S01-001-PRO-06` |
| `anchor_id` | `ANC-PRO-MXC-001` |
| `source_id` | `SRC-MXC` |
| `source_path` | `mxchat-basic/includes/class-mxchat-integrator.php` |
| `symbol_or_scope` | `mxchat_handle_chat_request`, `interpret_query_with_openai`, `interpret_query_with_claude`, `interpret_query_with_gemini`, `interpret_query_with_xai`, `interpret_query_with_deepseek`, provider streaming methods |
| `excerpt_summary` | Monolithic integrator class (10632 lines) handling all provider routing inline. Supports 5 providers: OpenAI, Claude, Gemini, DeepSeek, xAI Grok. Model selection by prefix detection (gpt-, claude-, grok-, deepseek-, gemini-). Full SSE streaming via cURL WRITEFUNCTION callback with provider-specific chunk parsing. Rate limiting, session ownership verification, moderation integration (MX_Chat_Moderation), ban handling. No dedicated provider abstraction layer; routing embedded in request handler. |
| `token_estimate` | `6000` |
| `secondary_domains` | `["DOM-SECURITY"]` |
| `notes` | No interface contract or registry pattern. Provider logic mixed with runtime, RAG, and session handling. Bot-specific options support multiple bot configurations. |

---

### Slice 7 - MxChat Admin (Provider Config)

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S01-001-PRO-07` |
| `anchor_id` | `ANC-PRO-MXC-001` |
| `source_id` | `SRC-MXC` |
| `source_path` | `mxchat-basic/includes/class-mxchat-admin.php` |
| `symbol_or_scope` | `initialize_default_options`, `perform_streaming_test`, `process_streaming_test_data` |
| `excerpt_summary` | Admin class (9036 lines) with provider configuration: separate API key fields per provider (OpenAI, Claude, xAI, Gemini, DeepSeek), streaming test facility that validates real API connectivity per provider, model defaults (gpt-5.1-chat-latest), 70+ configurable options including rate limiting, embedding model, and vector store integration. Provider-specific streaming test endpoints with SSE parsing. License-gated features. Nonce and capability checks on all AJAX handlers. |
| `token_estimate` | `3500` |
| `secondary_domains` | `["DOM-ADMIN"]` |
| `notes` | 5-provider API key management. Streaming connectivity test per provider. Default model: gpt-5.1-chat-latest. |

---

### Slice 8 - Baachal Provider Logic

| Field | Value |
|---|---|
| `slice_id` | `CP-P01-S01-001-PRO-08` |
| `anchor_id` | `ANC-PRO-BAA-001` |
| `source_id` | `SRC-BAA` |
| `source_path` | `baachal/baachal.php`, `baachal/admin/tabs/general.php` |
| `symbol_or_scope` | `call_ai_api`, `call_gemini_api`, `call_openai_api`, `call_claude_api`, `call_grok_api`, general settings tab |
| `excerpt_summary` | Inline provider functions in main plugin class (2569 lines total). 4 providers: Gemini, OpenAI, Claude, Grok. Simple switch-case dispatcher (`call_ai_api`) routing to per-provider methods. Each method builds API requests directly with `wp_remote_post()`, hardcoded temperature (0.7), max_tokens (1024), 30s timeout. No provider abstraction, no interface contract, no registry. Conversation history support via message array building. Website context injection via `get_website_context()`. Safety filter handling (Gemini). Quota/overload error messaging (Gemini). Settings tab provides dropdown provider selection with conditional API key/model fields. Model catalog in HTML select options (20+ models). |
| `token_estimate` | `3000` |
| `secondary_domains` | `["DOM-ADMIN"]` |
| `notes` | No streaming support. No tool calling. No fallback chain. No retry logic. No model registry or resolution. Hardcoded parameters. Clean but minimal. |

---

## Pack Summary

| Field | Value |
|---|---|
| `total_slices` | `8` |
| `total_token_estimate` | `48000` |

### Domain Coverage Matrix

| Domain | SRC-AXI | SRC-MXC | SRC-BAA |
|---|---|---|---|
| `DOM-PROVIDER` | `CP-P01-S01-001-PRO-01` through `PRO-05` | `CP-P01-S01-001-PRO-06`, `PRO-07` | `CP-P01-S01-001-PRO-08` |

### Gaps and Risks

- **Abstraction asymmetry**: SRC-AXI has a formal registry + interface + per-provider adapters; SRC-MXC has inline routing in monolithic integrator; SRC-BAA has simple per-provider functions. Scoring must assess behavioral outcomes, not structural elegance alone.
- **Provider count variance**: SRC-MXC supports 5 providers (adds DeepSeek, xAI); SRC-AXI supports 3 (OpenAI, Claude, Gemini); SRC-BAA supports 4 (adds Grok). Coverage scoring should account for extensibility rather than raw count.
- **Streaming coverage**: SRC-MXC has full SSE streaming; SRC-AXI has streaming in Gemini provider (others at transport layer); SRC-BAA has no streaming. This affects reliability and operability scoring.
- **Tool calling**: SRC-AXI has sophisticated multi-round tool calling with handshake pattern across all 3 providers; SRC-MXC has basic intent/action system; SRC-BAA has none. This is primarily DOM-TOOLS but affects DOM-PROVIDER extensibility.
- **Model registry**: SRC-AXI has centralized model catalog with 35+ models, aliases, fallback chains, pricing, and JS payload; SRC-MXC has ad-hoc model detection by prefix; SRC-BAA has hardcoded HTML select options.
