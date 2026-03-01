# Runtime Feature Entries

**Session ID**: `phase01-session02-runtime-and-migration-domains`
**Domain**: `DOM-RUNTIME`
**Context Pack**: `CP-P01-S02-001`
**Created**: 2026-03-01

---

## SRC-AXI Feature Entries

### FEAT-AXI-RUN-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-RUN-001` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-RUN-AXI-001` |
| `source_path` | `axiachat-ai/includes/class-aichat-ajax.php` |
| `domain_id` | `DOM-RUNTIME` |
| `capability_name` | `Message lifecycle and request pipeline` |
| `capability_summary` | AJAX-based message handling via `wp_ajax_aichat_process_message`. Applies pre-processing filter chain (captcha, honeypot, rate limiting, spam/moderation), builds message array (system prompt + history + current message), dispatches to provider registry, handles tool-pending state for GPT-5 handshake pattern (tool_pending -> client execution -> continuation AJAX call), processes response through markdown rendering and HTML sanitization (`wp_kses_post`), returns structured JSON with optional suggestions. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-SECURITY"]` |

---

### FEAT-AXI-RUN-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-RUN-002` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-RUN-AXI-001` |
| `source_path` | `axiachat-ai/includes/class-aichat-ajax.php` |
| `domain_id` | `DOM-RUNTIME` |
| `capability_name` | `Session handling and conversation state` |
| `capability_summary` | Session state stored in WP options keyed by session_id. History retrieval via `build_history_messages()` with configurable max messages (default 20) and context max length (4096 chars). Session ID generated or validated via `aichat_sanitize_session_id()`. Optional conversation logging to `wp_aichat_conversations` table with user_id, tokens, and cost tracking per message. Bot-slug routing supports multi-bot configurations. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |

---

### FEAT-AXI-RUN-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-RUN-003` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-RUN-AXI-001` |
| `source_path` | `axiachat-ai/includes/class-aichat-ajax.php` |
| `domain_id` | `DOM-RUNTIME` |
| `capability_name` | `Streaming and real-time delivery` |
| `capability_summary` | [N/A] No native streaming implementation in the AJAX handler. All responses are full-text JSON via `wp_send_json_success()`. Provider adapters have streaming endpoints (Gemini `:streamGenerateContent`) but the runtime pipeline does not expose SSE to the frontend. Tool-pending handshake pattern provides a form of asynchronous flow but is not real-time streaming. |
| `implementation_status` | `na_no_matching_implementation` |
| `maturity_level` | `na_no_matching_implementation` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |
| `na_reason` | `No SSE or chunked response delivery in the AJAX runtime pipeline. Provider-level streaming exists but is not exposed through the request handler to the frontend.` |

---

### FEAT-AXI-RUN-004

| Field | Value |
|---|---|
| `feature_id` | `FEAT-AXI-RUN-004` |
| `source_plugin` | `SRC-AXI` |
| `source_anchor_id` | `ANC-RUN-AXI-001` |
| `source_path` | `axiachat-ai/assets/js/aichat-frontend.js` |
| `domain_id` | `DOM-RUNTIME` |
| `capability_name` | `Frontend state management and UX runtime` |
| `capability_summary` | jQuery-based frontend managing chat widget lifecycle. Session ID in cookie (UUID, 30-day expiry). Double-submit prevention via `data('aichatInFlight')`. Usage limit tracking via `data('aichatLimited')`. Thinking stages (0s dots, 3s "Thinking", 6s "Still working"). DOM: `.aichat-messages` with user/bot message divs, suggestion chips, error displays. Draggable widget. Auto-scroll. Config via `AIChatVars` localization. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |

---

## SRC-MXC Feature Entries

### FEAT-MXC-RUN-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-RUN-001` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-RUN-MXC-001` |
| `source_path` | `mxchat-basic/includes/class-mxchat-integrator.php` |
| `domain_id` | `DOM-RUNTIME` |
| `capability_name` | `Message lifecycle and request pipeline` |
| `capability_summary` | Dual-mode request handling: standard AJAX (`mxchat_handle_chat_request`) and streaming (`mxchat_stream_chat`). Message intake with sanitization, contextual awareness (page content/title injection), vision processing flags. Provider dispatch via model prefix detection. Rate limiting and moderation (MX_Chat_Moderation) integrated inline. Chat mode tracking (AI/agent/livechat) per session. Bot-specific option overrides for multi-bot configurations. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-SECURITY"]` |

---

### FEAT-MXC-RUN-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-RUN-002` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-RUN-MXC-001` |
| `source_path` | `mxchat-basic/includes/class-mxchat-integrator.php` |
| `domain_id` | `DOM-RUNTIME` |
| `capability_name` | `Session handling and conversation state` |
| `capability_summary` | Session stored in WP options keyed by `mxchat_history_{session_id}`. Per-bot sessions via separate cookie per bot instance. Session ownership validation compares IP/user against `mxchat_session_owner_{session_id}` -- triggers automatic session reset on mismatch (prevents session hijacking). Persistence filtering via `sessionStartTimestamp` when persistence is OFF (only shows current page-load messages). Chat mode per session tracked in `mxchat_mode_{session_id}`. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-SECURITY"]` |

---

### FEAT-MXC-RUN-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-RUN-003` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-RUN-MXC-001` |
| `source_path` | `mxchat-basic/includes/class-mxchat-integrator.php` |
| `domain_id` | `DOM-RUNTIME` |
| `capability_name` | `SSE streaming and real-time delivery` |
| `capability_summary` | Full Server-Sent Events streaming implementation. Backend: cURL WRITEFUNCTION callbacks with provider-specific chunk parsing for 5 providers (OpenAI, Claude, Gemini, DeepSeek, Grok). SSE headers (Content-Type: text/event-stream, Cache-Control: no-cache, X-Accel-Buffering: no), output buffer control. Frontend: native Fetch API with ReadableStream, chunk accumulation into `accumulatedContent`, incremental DOM updates via `updateStreamingMessage()`. Completion signal: `data: [DONE]\n\n`. Automatic non-streaming fallback if stream response is JSON. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `external_ai_provider_required` |
| `risk_level` | `medium` |
| `secondary_domains` | `["DOM-PROVIDER"]` |

---

### FEAT-MXC-RUN-004

| Field | Value |
|---|---|
| `feature_id` | `FEAT-MXC-RUN-004` |
| `source_plugin` | `SRC-MXC` |
| `source_anchor_id` | `ANC-RUN-MXC-001` |
| `source_path` | `mxchat-basic/js/chat-script.js` |
| `domain_id` | `DOM-RUNTIME` |
| `capability_name` | `Frontend state management and UX runtime` |
| `capability_summary` | jQuery + Fetch API frontend with multi-bot instance management. `MxChatInstances` registry tracks per-bot state: sessionId (cookie), isStreaming, sessionStartTimestamp, processedMessageIds (dedup for polling). DOM: per-bot containers (`#chat-box-{botId}`), textarea with auto-resize, popular question chips, PDF/Word upload toolbar. State: `disableChatInput()`/`enableChatInput()` lock during requests. Bot HTML sanitized via `sanitizeBotHtml()` with DOMParser whitelist. User messages escaped via `escapeHtml()`. HTTP status code mapping (403, 429, 500). |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_stable` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |

---

## SRC-BAA Feature Entries

### FEAT-BAA-RUN-001

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-RUN-001` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-RUN-BAA-001` |
| `source_path` | `baachal/baachal.php` |
| `domain_id` | `DOM-RUNTIME` |
| `capability_name` | `Message lifecycle and request pipeline` |
| `capability_summary` | AJAX-based message handling via `baachal_message` action. Sanitizes input (`sanitize_text_field`), validates message length (1000 char max), generates/validates session ID (`chat_{uuid}` format). Custom handler support via `baachal_custom_message_handler` filter (can completely replace AI processing). Provider dispatch via `call_ai_api()`. Response filtered through `baachal_bot_response` hook. WooCommerce product card injection. Both user and bot messages saved to conversation post meta. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |
| `secondary_domains` | `["DOM-WOOCOMMERCE"]` |

---

### FEAT-BAA-RUN-002

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-RUN-002` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-RUN-BAA-001` |
| `source_path` | `baachal/baachal.php` |
| `domain_id` | `DOM-RUNTIME` |
| `capability_name` | `Session handling and conversation state` |
| `capability_summary` | Conversation storage via custom post type `baachal_conversation` with post meta `_chat_messages` (array of `{type, message, timestamp}`). Session metadata: `_session_id`, `_user_id`, `_user_ip`, `_user_agent`. Memory window configurable via `baachal_memory_limit` filter (default 10 messages). Max 100 messages per conversation via `baachal_message_limit`. Persistence controlled by `baachal_message_persistence` option. Session ID from localStorage on frontend. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |

---

### FEAT-BAA-RUN-003

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-RUN-003` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-RUN-BAA-001` |
| `source_path` | `baachal/baachal.php` |
| `domain_id` | `DOM-RUNTIME` |
| `capability_name` | `Streaming and real-time delivery` |
| `capability_summary` | [N/A] No streaming implementation. All responses are full-text via standard AJAX JSON (`wp_send_json_success()`). No SSE headers, no chunked response delivery, no cURL streaming callbacks. |
| `implementation_status` | `na_no_matching_implementation` |
| `maturity_level` | `na_no_matching_implementation` |
| `dependency_profile` | `plugin_internal_modules` |
| `risk_level` | `low` |
| `na_reason` | `No streaming capability implemented. All chat responses are synchronous full-text JSON.` |

---

### FEAT-BAA-RUN-004

| Field | Value |
|---|---|
| `feature_id` | `FEAT-BAA-RUN-004` |
| `source_plugin` | `SRC-BAA` |
| `source_anchor_id` | `ANC-RUN-BAA-001` |
| `source_path` | `baachal/assets/chatbot.js` |
| `domain_id` | `DOM-RUNTIME` |
| `capability_name` | `Frontend state management and UX runtime` |
| `capability_summary` | jQuery frontend with localStorage-based session management. UI state: `isOpen` (widget visibility), `isLoading` (in-flight flag). DOM: toggle button, container, messages area with typed divs (`.user-message`, `.bot-message`), textarea with auto-resize, loading indicator. User messages escaped via `escapeHtml()`. Bot HTML sanitized via `sanitizeBotHtml()` DOMParser whitelist (safe href patterns). Extensive JS hook system: before_send, response_received, filter_bot_response, before/after_open/close, before/after_add_message. Clear history with session reset. Widget template rendered separately from PHP template. |
| `implementation_status` | `implemented` |
| `maturity_level` | `production_limited` |
| `dependency_profile` | `wordpress_core_only` |
| `risk_level` | `low` |

---

## Feature Entry Summary

| Source | Feature Count | Implemented | N/A |
|--------|---------------|-------------|-----|
| `SRC-AXI` | 4 | 3 | 1 (streaming) |
| `SRC-MXC` | 4 | 4 | 0 |
| `SRC-BAA` | 4 | 3 | 1 (streaming) |
| **Total** | **12** | **10** | **2** |
