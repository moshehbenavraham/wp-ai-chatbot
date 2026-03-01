# Provider Feature Entries

**Session ID**: `phase01-session01-scoring-framework-and-provider-domain`
**Domain**: `DOM-PROVIDER`
**Context Pack**: `CP-P01-S01-001`
**Created**: 2026-03-01

---

## SRC-AXI Feature Entries

### FEAT-AXI-PRO-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-PRO-001` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-PRO-AXI-001` |
| `source_path` | `axiachat-ai/includes/class-aichat-provider-registry.php` |
| `domain_id` | `DOM-PROVIDER` |
| `capability_name` | `Provider registry and adapter dispatch` |
| `capability_summary` | Singleton + Factory registry that manages provider lifecycle: dynamic registration via hook (`aichat_register_providers`), instance caching by provider ID and config hash, interface compliance validation (`AIChat_Provider_Interface`), provider enable/disable toggling, and alias normalization (anthropic -> claude). |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-ADMIN"]` |

---

### FEAT-AXI-PRO-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-PRO-002` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-PRO-AXI-001` |
| `source_path` | `axiachat-ai/includes/providers/class-openai-provider.php` |
| `domain_id` | `DOM-PROVIDER` |
| `capability_name` | `OpenAI provider adapter with dual-API routing` |
| `capability_summary` | OpenAI adapter auto-routing GPT-5.x to Responses API and others to Chat Completions API. Multi-round tool calling (up to 5 rounds), GPT-5 handshake pattern for client-side tool execution, agency proxy fallback for auth, per-model cost calculation in microcents. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `external_ai_provider_required` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-SECURITY"]` |

---

### FEAT-AXI-PRO-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-PRO-003` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-PRO-AXI-001` |
| `source_path` | `axiachat-ai/includes/providers/class-claude-provider.php` |
| `domain_id` | `DOM-PROVIDER` |
| `capability_name` | `Claude provider adapter with fallback chain` |
| `capability_summary` | Anthropic Claude adapter with multi-round tool calling, handshake pattern for client-side tool execution, DB-backed tool state persistence (`wp_aichat_tool_states`), 4-tier fallback chain via model registry, and exponential backoff retry (3 attempts for 429/503/529). Web search integration via filter. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `external_ai_provider_required` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-SECURITY"]` |

---

### FEAT-AXI-PRO-004

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-PRO-004` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-PRO-AXI-001` |
| `source_path` | `axiachat-ai/includes/providers/class-gemini-provider.php` |
| `domain_id` | `DOM-PROVIDER` |
| `capability_name` | `Gemini provider adapter with native tool support` |
| `capability_summary` | Google Gemini adapter handling Gemini-native message format (`contents[]`/`parts[]`), separate `systemInstruction` field, `generationConfig` object. Thinking mode management per model tier. Streaming via `:streamGenerateContent` endpoint. Multi-tool support combining native `googleSearch`/`codeExecution` with custom `functionDeclarations`. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `external_ai_provider_required` |
| `risk_level` | `medium` |

---

### FEAT-AXI-PRO-005

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-PRO-005` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-PRO-AXI-001` |
| `source_path` | `axiachat-ai/includes/model-registry.php` |
| `domain_id` | `DOM-PROVIDER` |
| `capability_name` | `Centralized model registry and resolution` |
| `capability_summary` | Single source of truth for 35+ models across 3 providers. Per-model metadata: context window, max output, pricing, aliases, deprecated IDs, fallback order. Model resolution (exact -> alias -> prefix -> default), fallback chains (Anthropic 4-tier, Gemini 3-tier), legacy pricing bridge, and JS payload builder for admin UI. Filterable via `aichat_model_registry` hook. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-ADMIN"]` |

---

## SRC-MXC Feature Entries

### FEAT-MXC-PRO-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-PRO-001` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-PRO-MXC-001` |
| `source_path` | `mxchat-basic/includes/class-mxchat-integrator.php` |
| `domain_id` | `DOM-PROVIDER` |
| `capability_name` | `Multi-provider inline routing and chat dispatch` |
| `capability_summary` | Monolithic integrator class routing chat requests to 5 providers (OpenAI, Claude, Gemini, DeepSeek, xAI Grok) via model prefix detection. No dedicated provider abstraction layer; routing logic embedded in main request handler alongside session management, RAG, and moderation. Bot-specific options support multiple bot configurations with distinct provider settings. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `external_ai_provider_required` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-RUNTIME"]` |

---

### FEAT-MXC-PRO-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-PRO-002` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-PRO-MXC-001` |
| `source_path` | `mxchat-basic/includes/class-mxchat-integrator.php` |
| `domain_id` | `DOM-PROVIDER` |
| `capability_name` | `SSE streaming with provider-specific parsing` |
| `capability_summary` | Full Server-Sent Events streaming implementation using cURL WRITEFUNCTION callbacks. Provider-specific chunk parsing for OpenAI, Claude, Gemini, DeepSeek, and Grok SSE formats. Headers management (Content-Type: text/event-stream, Cache-Control: no-cache, X-Accel-Buffering: no), output buffer control. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `external_ai_provider_required` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-RUNTIME"]` |

---

### FEAT-MXC-PRO-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-PRO-003` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-PRO-MXC-001` |
| `source_path` | `mxchat-basic/includes/class-mxchat-admin.php` |
| `domain_id` | `DOM-PROVIDER` |
| `capability_name` | `Provider configuration and connectivity testing` |
| `capability_summary` | Admin interface for 5-provider API key management with per-provider streaming connectivity tests. Default options include model selection (gpt-5.1-chat-latest), temperature, max_tokens, embedding model. Streaming test facility validates real API connectivity and SSE parsing per provider. Nonce and capability checks on AJAX handlers. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-ADMIN"]` |

---

## SRC-BAA Feature Entries

### FEAT-BAA-PRO-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-PRO-001` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-PRO-BAA-001` |
| `source_path` | `baachal/baachal.php` |
| `domain_id` | `DOM-PROVIDER` |
| `capability_name` | `Inline multi-provider API dispatch` |
| `capability_summary` | Simple switch-case dispatcher (`call_ai_api`) routing to per-provider methods for Gemini, OpenAI, Claude, and Grok. Each method constructs API requests directly with `wp_remote_post()`, hardcoded parameters (temperature 0.7, max_tokens 1024, timeout 30s). Conversation history support via message array building. Website context injection via `get_website_context()`. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `external_ai_provider_required` |
| `risk_level` | `medium` |

---

### FEAT-BAA-PRO-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-PRO-002` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-PRO-BAA-001` |
| `source_path` | `baachal/baachal.php` |
| `domain_id` | `DOM-PROVIDER` |
| `capability_name` | `Provider-specific error handling and safety filters` |
| `capability_summary` | Per-provider error handling with Gemini-specific safety filter detection (`finishReason: SAFETY`), quota/rate-limit messaging (429), overload detection, and HTTP status-based error classification. Consistent error response shape (`{success: false, error: string}`). User-friendly error suggestions (retry, check config, check quota). |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `external_ai_provider_required` |
| `risk_level` | `low` |

---

### FEAT-BAA-PRO-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-PRO-003` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-PRO-BAA-001` |
| `source_path` | `baachal/admin/tabs/general.php` |
| `domain_id` | `DOM-PROVIDER` |
| `capability_name` | `Admin provider selection and model catalog` |
| `capability_summary` | Settings tab with dropdown provider selection (Gemini, OpenAI, Claude, Grok), conditional API key fields (password input type), and per-provider model catalog in HTML select options (20+ models with descriptive labels). Provider-specific UI visibility toggling. Proper escaping (`esc_attr`, `checked`, `selected`). |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-ADMIN"]` |

---

## Feature Entry Summary

| Source | Feature Count | Implementation Status |
|--------|---------------|----------------------|
| `SRC-AXI` | 5 | All `implemented` |
| `SRC-MXC` | 3 | All `implemented` |
| `SRC-BAA` | 3 | All `implemented` |
| **Total** | **11** | **11 implemented, 0 N/A** |
