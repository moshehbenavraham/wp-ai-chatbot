# Endpoint Specifications

**Session**: `phase02-session04-chat-runtime-session-model-and-frontend-contracts`
**Version**: 1.0
**Created**: 2026-03-01
**Module**: `includes/runtime/class-chat-endpoints.php`, `includes/runtime/class-session-rest.php`
**Satisfies**: T006 (endpoint routing/auth model), T009 (endpoint specifications)

---

## 1. Overview

This document specifies every HTTP endpoint for the WPAIC chat runtime. Each endpoint
is defined with enough precision for parallel frontend and backend implementation:
route, method, authentication, request schema with validation rules, response schema
with types, error codes, and example payloads.

### 1.1 Routing Decision Rationale

Endpoints are split across two transport mechanisms based on the nature of each
operation:

| Transport | Endpoints | Rationale |
|-----------|-----------|-----------|
| **AJAX** (`admin-ajax.php`) | Chat message send (sync), chat streaming (SSE) | Public-facing, high-frequency, anonymous-accessible via `wp_ajax_nopriv_` hooks. AJAX allows direct output control needed for SSE streaming without REST response wrapping. All three source plugins use AJAX exclusively for chat dispatch. |
| **REST API** (`/wp-json/wp-ai-chatbot/v1/`) | Session CRUD, history retrieval | Resource-oriented operations that benefit from REST semantics (proper HTTP verbs, structured JSON responses, built-in schema validation). Aligns with existing `lib/health-endpoint.php` and `lib/rate-limiter.php` which already use the REST namespace. |

**Why not REST for chat?** The streaming endpoint requires fine-grained output control
(`text/event-stream` headers, output buffer flushing, `exit` on completion). WordPress
REST API wraps all responses through `rest_ensure_response()`, which is incompatible
with raw SSE output. AJAX handlers have full control over the response lifecycle.

**Why not AJAX for sessions?** Session management operations are resource-oriented CRUD
operations (`POST /sessions`, `GET /sessions/{token}`, `DELETE /sessions/{token}`)
that map naturally to REST verbs. REST also provides built-in parameter validation,
schema enforcement, and pagination headers.

### 1.2 Reference Anchors

| Source Plugin | File | Lines | Relevance |
|---------------|------|-------|-----------|
| AxiaChat | `axiachat-ai/includes/class-aichat-ajax.php` | 39-42 | AJAX registration: `wp_ajax_aichat_process_message` + `wp_ajax_nopriv_` for both logged-in and anonymous, plus `aichat_get_history` |
| AxiaChat | `axiachat-ai/includes/class-aichat-ajax.php` | 54-58 | Nonce verification: `wp_verify_nonce($nonce, 'aichat_ajax')` |
| AxiaChat | `axiachat-ai/includes/class-aichat-ajax.php` | 60-76 | Session ID as UUID v4, tool continuation (`continue_tool`), field sanitization |
| MxChat | `mxchat-basic/includes/class-mxchat-integrator.php` | 60-61 | AJAX registration: `wp_ajax_mxchat_handle_chat_request` + `wp_ajax_nopriv_` for sync |
| MxChat | `mxchat-basic/includes/class-mxchat-integrator.php` | 101-102 | AJAX registration: `wp_ajax_mxchat_stream_chat` + `wp_ajax_nopriv_` for streaming (same handler) |
| MxChat | `mxchat-basic/js/chat-script.js` | 47-56 | Cookie-based per-bot session management, multi-bot via `bot_id` field |
| Baachal | `baachal/baachal.php` | 62-67 | AJAX registration: `baachal_message` + `nopriv`, separate `baachal_get_chat_history` and `baachal_clear_chat_history` actions |
| Baachal | `baachal/assets/chatbot.js` | 130-134, 443-478 | Frontend AJAX calls with nonce |

### 1.3 Cross-References

| Document | Relevance |
|----------|-----------|
| `session_model_contract.md` | Session CRUD interface, ownership validation, expiry policy |
| `message_lifecycle_pipeline.md` | Pipeline stages that AJAX chat endpoints invoke |
| `streaming_delivery_contract.md` | SSE protocol, chunk rendering, connection lifecycle |
| Session 02 `database_table_schemas.md` | `wp_wpaic_sessions`, `wp_wpaic_messages`, `wp_wpaic_bots` schemas |
| Session 02 `options_schema.md` | `wpaic_security_rate_limit`, `wpaic_security_rate_window` option keys |
| Session 03 `streaming_transport_layer.md` | SSE wire format, normalized chunk types, HTTP headers |
| Session 03 `error_handling_fallback_contract.md` | Error taxonomy (4-tier), standard error payload |
| Session 03 `cost_accounting_interface.md` | Cost tracking hooks fired after provider request |
| `lib/rate-limiter.php` | `wpaic_check_rate_limit()`, `wpaic_rate_limited_permission()`, `wpaic_get_client_ip()` |

### 1.4 Namespace and Prefix Conventions

| Item | Value |
|------|-------|
| AJAX action prefix | `wpaic_` |
| REST namespace | `wp-ai-chatbot/v1` |
| Nonce action (chat) | `wpaic_chat` |
| Nonce action (REST) | `wp_rest` (WordPress built-in) |
| Hook prefix | `wpaic_` |
| Localized config object | `wpaicChatData` |

---

## 2. Endpoint Registry

| # | Route | Method | Type | Auth | Purpose |
|---|-------|--------|------|------|---------|
| 1 | `admin-ajax.php?action=wpaic_chat` | POST | AJAX | Nonce (`wpaic_chat`) | Send chat message (sync response) |
| 2 | `admin-ajax.php?action=wpaic_chat_stream` | POST | AJAX/SSE | Nonce (`wpaic_chat`) | Send chat message (streaming SSE response) |
| 3 | `/wp-json/wp-ai-chatbot/v1/sessions` | POST | REST | Public (rate limited) | Create a new chat session |
| 4 | `/wp-json/wp-ai-chatbot/v1/sessions/{token}` | GET | REST | Ownership validation | Resume / get session details |
| 5 | `/wp-json/wp-ai-chatbot/v1/sessions/{token}/close` | POST | REST | Ownership validation | Close a session |
| 6 | `/wp-json/wp-ai-chatbot/v1/sessions/{token}/history` | GET | REST | Ownership validation | Get message history |
| 7 | `/wp-json/wp-ai-chatbot/v1/sessions/{token}` | DELETE | REST | `manage_options` | Delete session and all data (admin only) |

### AJAX Registration

```php
// includes/runtime/class-chat-endpoints.php
add_action( 'wp_ajax_wpaic_chat', array( $this, 'handle_chat' ) );
add_action( 'wp_ajax_nopriv_wpaic_chat', array( $this, 'handle_chat' ) );
add_action( 'wp_ajax_wpaic_chat_stream', array( $this, 'handle_chat_stream' ) );
add_action( 'wp_ajax_nopriv_wpaic_chat_stream', array( $this, 'handle_chat_stream' ) );
```

Ref: AXI singleton constructor registering `wp_ajax_aichat_process_message` (lines 39-40), MXC integrator registering `wp_ajax_mxchat_handle_chat_request` (lines 60-61) + `wp_ajax_mxchat_stream_chat` (lines 101-102), BAA hook array with `wp_ajax_baachal_message` (lines 62-63).

### REST Registration

```php
// includes/runtime/class-session-rest.php
add_action( 'rest_api_init', function () {
    $controller = new WPAIC_Session_Controller();
    $controller->register_routes();
} );
```

---

## 3. AJAX Endpoint 1: Chat Message Send (Sync)

### 3.1 Route Definition

| Property | Value |
|----------|-------|
| URL | `{site_url}/wp-admin/admin-ajax.php` |
| Method | POST |
| Content-Type | `application/x-www-form-urlencoded` (FormData) |
| Action | `wpaic_chat` |
| WordPress Hooks | `wp_ajax_wpaic_chat`, `wp_ajax_nopriv_wpaic_chat` |
| Handler | `Chat_Endpoints::handle_chat()` |
| Pipeline | Invokes the full message lifecycle pipeline (Stages 1-4) |

### 3.2 Authentication

| Requirement | Details |
|-------------|---------|
| Nonce | Required. Field: `nonce`. Action: `wpaic_chat`. Generated via `wp_create_nonce('wpaic_chat')` and injected via `wp_localize_script()`. |
| Capability | None (public endpoint). Both `wp_ajax_` and `wp_ajax_nopriv_` hooks are registered. |
| Session ownership | Validated in pipeline Stage 1.5 (see `message_lifecycle_pipeline.md`). |

Ref: AXI `wp_verify_nonce($nonce, 'aichat_ajax')` (line 55), MXC nonce via `wp_localize_script`, BAA nonce via `baachal_ajax` localized object.

### 3.3 Request Schema

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `action` | string | Yes | Exact: `wpaic_chat` | WordPress AJAX action identifier |
| `nonce` | string | Yes | `sanitize_text_field()` then `wp_verify_nonce($val, 'wpaic_chat')` | WordPress nonce token |
| `message` | string | Yes (unless `continue_tool=1`) | `sanitize_textarea_field()`, non-empty after trim, `mb_strlen <= bot.input_max_length` (default 512, absolute max 4000) | User message text |
| `bot_slug` | string | No | `sanitize_title()`, resolved via priority chain if empty | Target bot identifier |
| `session_token` | string | No | UUID v4 regex: `/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i`. Empty triggers new session creation. | Session identifier for multi-turn context |
| `page_context` | string (JSON) | No | `wp_kses()` with empty allowlist after JSON decode. Max 3000 chars of extracted content. | Page context data (URL, title, content excerpt) |
| `wpaic_hp` | string | No | Must be empty (honeypot). Non-empty = bot, silently rejected. | Hidden anti-spam field |
| `continue_tool` | string | No | Boolean cast via `filter_var(..., FILTER_VALIDATE_BOOLEAN)` | Tool continuation flag (Phase 2 of two-phase model) |
| `response_id` | string | No (required if `continue_tool=1`) | `sanitize_text_field()`, UUID v4 format | Response ID from the tool-pending response |
| `tool_results` | string (JSON) | No (required if `continue_tool=1`) | JSON decode, array of `{call_id, name, result}` objects | Tool execution results |

Ref: AXI lines 69-76 (message, bot_slug, session_id sanitization), AXI lines 60-65 (continue_tool, response_id).

### 3.4 Response Schema -- Success

HTTP Status: `200`

```json
{
    "success": true,
    "data": {
        "message": "Our return policy allows returns within 30 days of purchase.",
        "session_token": "550e8400-e29b-41d4-a716-446655440000",
        "suggestions": ["How do I start a return?", "What items are non-returnable?"]
    }
}
```

| Field | Type | Always Present | Description |
|-------|------|----------------|-------------|
| `success` | boolean | Yes | Always `true` for success responses |
| `data.message` | string | Yes | Sanitized AI response (HTML via `wp_kses` allowlist) |
| `data.session_token` | string | Yes | Session token (returned on creation or echoed for existing) |
| `data.suggestions` | string[] | No | Suggestion chip texts; present when bot has suggestions enabled and response contains markers. Empty array if none extracted. |

### 3.5 Response Schema -- Tool Pending

HTTP Status: `200`

```json
{
    "success": true,
    "data": {
        "status": "tool_pending",
        "response_id": "660e8400-e29b-41d4-a716-446655440001",
        "tool_calls": [
            {
                "id": "call_abc123",
                "type": "function",
                "function": {
                    "name": "get_weather",
                    "arguments": "{\"location\":\"San Francisco\"}"
                }
            }
        ],
        "session_token": "550e8400-e29b-41d4-a716-446655440000",
        "bot_slug": "default",
        "model": "gpt-4o"
    }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `data.status` | string | Always `"tool_pending"` -- signals frontend to execute tools and send continuation |
| `data.response_id` | string | UUID for correlating the continuation request |
| `data.tool_calls` | array | Tool call objects per session 03 canonical format |
| `data.tool_calls[].id` | string | Provider-assigned call ID |
| `data.tool_calls[].type` | string | Always `"function"` |
| `data.tool_calls[].function.name` | string | Tool function name |
| `data.tool_calls[].function.arguments` | string | JSON-encoded arguments string |
| `data.session_token` | string | Current session token |
| `data.bot_slug` | string | Current bot slug |
| `data.model` | string | Model ID used for this request |

Ref: AXI tool continuation pattern (`process_tool_continuation()`, lines 100-127).

### 3.6 Response Schema -- Soft Rejection

HTTP Status: `200`

Moderation rejection:

```json
{
    "success": true,
    "data": {
        "moderated": true,
        "message": "I'm unable to process that request.",
        "session_token": "550e8400-e29b-41d4-a716-446655440000"
    }
}
```

Duplicate message:

```json
{
    "success": true,
    "data": {
        "duplicate": true,
        "session_token": "550e8400-e29b-41d4-a716-446655440000"
    }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `data.moderated` | boolean | Present and `true` when message was moderation-rejected |
| `data.duplicate` | boolean | Present and `true` when message was a duplicate within the 10s dedup window |
| `data.message` | string | Rejection message (only on moderation); configurable via `wpaic_security_rejection_message` option |

Soft rejections return `success: true` with a flag so the frontend differentiates them from hard errors and renders appropriate UI (e.g., "Message already sent" notice instead of error styling).

Ref: AXI lines 166-184 duplicate detection (`aichat_dup` code), AXI lines 186-200 moderation pipeline.

### 3.7 Error Responses

| HTTP Status | Error Code | Message | Condition |
|-------------|-----------|---------|-----------|
| 400 | `wpaic_empty_message` | "Message cannot be empty." | Empty after `sanitize_textarea_field()` and trim |
| 400 | `wpaic_message_too_long` | "Message exceeds maximum length." | `mb_strlen > bot.input_max_length` |
| 400 | `wpaic_invalid_session_token` | "Invalid session token format." | Token does not match UUID v4 regex |
| 400 | `wpaic_invalid_tool_results` | "Invalid tool results format." | `continue_tool=1` but `tool_results` is not valid JSON array |
| 403 | `wpaic_nonce_failed` | "Security verification failed. Please refresh the page." | Nonce missing or verification failure |
| 403 | `wpaic_session_denied` | "Session access denied." | Ownership validation failure (no detail on which field) |
| 404 | `wpaic_bot_not_found` | "No active chatbot configuration found." | Bot slug does not match any active bot |
| 429 | `wpaic_rate_limited` | "Too many requests. Please wait a moment." | Rate limit exceeded (`lib/rate-limiter.php`) |
| 429 | `wpaic_usage_limited` | "Daily usage limit reached. Please try again tomorrow." | Daily message/token/cost limit exceeded |
| 500 | `wpaic_provider_error` | (mapped from error taxonomy) | AI provider returned an error; user_message from session 03 error taxonomy |
| 500 | `wpaic_internal_error` | "An unexpected error occurred. Please try again." | Unhandled exception in pipeline |

Error format:

```json
{
    "success": false,
    "data": {
        "code": "wpaic_rate_limited",
        "message": "Too many requests. Please wait a moment."
    }
}
```

### 3.8 Example Request

```
POST /wp-admin/admin-ajax.php HTTP/1.1
Content-Type: application/x-www-form-urlencoded

action=wpaic_chat&nonce=a1b2c3d4e5&message=What+is+your+return+policy%3F&bot_slug=default&session_token=550e8400-e29b-41d4-a716-446655440000&wpaic_hp=
```

---

## 4. AJAX Endpoint 2: Chat Streaming (SSE)

### 4.1 Route Definition

| Property | Value |
|----------|-------|
| URL | `{site_url}/wp-admin/admin-ajax.php` |
| Method | POST |
| Content-Type (request) | `application/x-www-form-urlencoded` (FormData) |
| Content-Type (response) | `text/event-stream; charset=UTF-8` |
| Action | `wpaic_chat_stream` |
| WordPress Hooks | `wp_ajax_wpaic_chat_stream`, `wp_ajax_nopriv_wpaic_chat_stream` |
| Handler | `Chat_Endpoints::handle_chat_stream()` |

Ref: MXC `wp_ajax_mxchat_stream_chat` / `wp_ajax_nopriv_mxchat_stream_chat` (lines 101-102), separate AJAX action for streaming.

### 4.2 Authentication

Identical to Section 3.2 (sync chat). Same nonce action `wpaic_chat`, same public access policy.

### 4.3 Request Schema

Identical to Section 3.3 **except**:

| Difference | Value |
|------------|-------|
| `action` | `wpaic_chat_stream` (instead of `wpaic_chat`) |
| `continue_tool` | Not supported. Tool continuation always uses the sync endpoint. |
| `response_id` | Not applicable. |
| `tool_results` | Not applicable. |

All other fields (`nonce`, `message`, `bot_slug`, `session_token`, `page_context`, `wpaic_hp`) are identical.

### 4.4 Streaming Decision

Before emitting SSE, the handler checks:

```php
$stream_enabled  = (bool) get_option( 'wpaic_runtime_stream_enabled', true );
$supports_stream = ( $provider instanceof Streamable_Provider_Interface )
                   && $provider->supports_streaming();
$use_streaming   = $stream_enabled && $supports_stream;
```

If streaming is unavailable, the handler falls back to a standard JSON response (same format as Section 3.4). The frontend detects this via the `Content-Type` header.

Ref: MXC lines 408-427 streaming detection by model prefix + toggle setting.

### 4.5 Response -- SSE Stream

HTTP Headers (set BEFORE first chunk):

```
Content-Type: text/event-stream; charset=UTF-8
Cache-Control: no-cache, no-store, must-revalidate
Connection: keep-alive
X-Accel-Buffering: no
```

**Initial padding** (prevents proxy buffering for streams < 1KB):

```
: <1KB padding spaces>\n\n
```

**Session token event** (emitted once, immediately after pre-processing):

```
data: {"type":"session","session_token":"550e8400-e29b-41d4-a716-446655440000"}\n\n
```

**Content chunks** (emitted for each text fragment):

```
data: {"type":"content","content":"Our return"}\n\n
data: {"type":"content","content":" policy allows"}\n\n
data: {"type":"content","content":" returns within 30 days."}\n\n
```

**Usage event** (emitted once, after provider completes):

```
data: {"type":"usage","usage":{"prompt_tokens":150,"completion_tokens":42,"total_tokens":192}}\n\n
```

**Suggestions event** (optional, after response processing):

```
data: {"type":"suggestions","suggestions":["How do I start a return?","What items are non-returnable?"]}\n\n
```

**Done event** (always the last event):

```
data: {"type":"done","finish_reason":"stop"}\n\n
```

### 4.6 SSE Event Schema

| Event Type | Required Fields | Optional Fields | When Emitted |
|-----------|----------------|-----------------|-------------|
| `session` | `type`, `session_token` | -- | Once, immediately after pre-processing succeeds |
| `content` | `type`, `content` | -- | Multiple times, one per text fragment from provider |
| `tool_call` | `type`, `tool_call` | -- | Multiple times, if model requests tool calls |
| `usage` | `type`, `usage` | -- | Once, after provider finishes (with `prompt_tokens`, `completion_tokens`, `total_tokens`) |
| `suggestions` | `type`, `suggestions` | -- | Once, if bot has suggestions enabled and response contains markers |
| `error` | `type`, `error` | -- | Once, if error occurs during streaming |
| `done` | `type` | `finish_reason` | Once, always the last event |

Chunk types `content`, `usage`, `error`, and `done` consume the session 03 `streaming_transport_layer.md` normalized format. The `session` and `suggestions` events are runtime additions not emitted by the provider adapter.

### 4.7 Mid-Stream Error

When an error occurs after content chunks have already been emitted:

```
data: {"type":"content","content":"Partial resp"}\n\n
data: {"type":"error","error":{"code":504,"type":"timeout","message":"Stream timeout exceeded.","retryable":false,"user_message":"The response took too long. Please try again."}}\n\n
data: {"type":"done"}\n\n
```

Partial content is preserved on the frontend. See `streaming_delivery_contract.md` Section 5.3.

### 4.8 JSON Fallback Response

When streaming is unavailable, the response is standard JSON:

```
Content-Type: application/json; charset=UTF-8
```

```json
{
    "success": true,
    "data": {
        "message": "Complete AI response.",
        "session_token": "550e8400-e29b-41d4-a716-446655440000",
        "suggestions": []
    }
}
```

The frontend detects JSON vs SSE via the `Content-Type` header and switches handling.

### 4.9 Pre-Stream Error Responses

Errors during pre-processing (before SSE headers are sent) return standard JSON:

| HTTP Status | Error Code | Condition |
|-------------|-----------|-----------|
| 400 | `wpaic_empty_message` | Empty message |
| 403 | `wpaic_nonce_failed` | Nonce failure |
| 403 | `wpaic_session_denied` | Ownership failure |
| 404 | `wpaic_bot_not_found` | Bot not found |
| 429 | `wpaic_rate_limited` | Rate limit exceeded |

Format is identical to Section 3.7 error format.

### 4.10 Handler Termination

The streaming handler MUST call `wp_die()` after the final `done` chunk to prevent WordPress from appending `0` to the output (standard AJAX termination).

### 4.11 Streaming HTTP Requirements

**Headers** (set before any output):

```php
header( 'Content-Type: text/event-stream; charset=UTF-8' );
header( 'Cache-Control: no-cache, no-store, must-revalidate' );
header( 'Connection: keep-alive' );
header( 'X-Accel-Buffering: no' );
```

**Output buffer cleanup**:

```php
while ( ob_get_level() ) {
    ob_end_flush();
}
if ( function_exists( 'apache_setenv' ) ) {
    apache_setenv( 'no-gzip', '1' );
}
ini_set( 'zlib.output_compression', '0' );
set_time_limit( 0 );
```

**SSE emission pattern**:

```php
function wpaic_emit_sse_event( $data ) {
    echo 'data: ' . wp_json_encode( $data ) . "\n\n";
    if ( ob_get_level() ) {
        ob_flush();
    }
    flush();
}
```

**Timeout management**:

| Timeout | Value | Enforced By | On Expiry |
|---------|-------|-------------|-----------|
| PHP execution | Unlimited | `set_time_limit( 0 )` | N/A |
| Stream total | 120 seconds | Runtime timer | Emit error + done events |
| Chunk stall | 30 seconds | Provider adapter (`CURLOPT_LOW_SPEED_TIME`) | Emit error + done events |
| Connect timeout | 5 seconds | Provider adapter (`CURLOPT_CONNECTTIMEOUT`) | Emit error + done events |

**Connection abort**: If the client disconnects mid-stream, the server detects via `connection_aborted()` and still persists partial response + session aggregates.

**Server compatibility**:

| Server | Requirement | Purpose |
|--------|------------|---------|
| Nginx | `proxy_buffering off;` or `X-Accel-Buffering: no` header | Prevent response buffering |
| Apache | No `mod_deflate` on `text/event-stream` | Prevent compression |
| Cloudflare | 1KB initial padding | Prevent small-response buffering |
| PHP-FPM | `fastcgi_buffering off;` in Nginx | Prevent FastCGI buffering |

Ref: Session 03 `streaming_transport_layer.md` (SSE protocol, chunk format, HTTP headers), `streaming_delivery_contract.md` (runtime SSE emission, frontend consumption, server compatibility).

---

## 5. REST Endpoint 3: Create Session

### 5.1 Route Definition

| Property | Value |
|----------|-------|
| URL | `/wp-json/wp-ai-chatbot/v1/sessions` |
| Method | POST |
| Content-Type | `application/json` |
| Authentication | Public (anonymous allowed, rate limited) |
| Handler | `WPAIC_Session_Controller::create_session()` |

### 5.2 Authentication

| Requirement | Details |
|-------------|---------|
| Nonce | Optional. Header `X-WP-Nonce` with `wp_rest` value sets `current_user` for logged-in requests. Anonymous requests omit the header. |
| Capability | None (public endpoint). Anonymous users can create sessions. |
| Rate limiting | `wpaic_rate_limited_permission('session_create', 30, 60)`. Max 30 creations per IP per 60 seconds. |

Ref: AXI implicit session creation in `process_message()` (line 74, `wp_generate_uuid4()`), MXC cookie-based per-bot session (lines 47-56), BAA localStorage session init (lines 6-20). WPAIC supports both implicit (first AJAX message) and explicit (this endpoint) creation.

### 5.3 Request Schema

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `bot_slug` | string | No | `sanitize_title()`, must match an active bot; defaults to global default bot via priority chain | Target bot identifier |
| `page_id` | integer | No | `absint()`, validated against existing published posts | WordPress page/post ID where session originates |

### 5.4 Response Schema -- Success

HTTP Status: `201 Created`

```json
{
    "session_token": "550e8400-e29b-41d4-a716-446655440000",
    "bot_slug": "default",
    "bot_name": "AI Assistant",
    "status": "active",
    "created_at": "2026-03-01T10:30:00Z",
    "expires_at": "2026-03-02T10:30:00Z",
    "config": {
        "stream_enabled": true,
        "input_max_length": 512,
        "placeholder": "Write your question...",
        "suggestions_enabled": false,
        "start_sentence": "Hello! How can I help you today?"
    }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `session_token` | string | UUID v4 token for the frontend to store in localStorage |
| `bot_slug` | string | Resolved bot slug |
| `bot_name` | string | Bot display name |
| `status` | string | Always `"active"` for new sessions |
| `created_at` | string | ISO 8601 creation timestamp (UTC) |
| `expires_at` | string | ISO 8601 expiry timestamp (UTC); TTL from `wpaic_runtime_session_ttl` option (default 86400s) |
| `config` | object | Bot-specific configuration for frontend rendering |
| `config.stream_enabled` | boolean | Whether streaming is available for this bot/provider combination |
| `config.input_max_length` | integer | Maximum user input characters |
| `config.placeholder` | string | Input placeholder text |
| `config.suggestions_enabled` | boolean | Whether suggestion chips are enabled |
| `config.start_sentence` | string or null | Initial greeting message; null if not configured |

### 5.5 Ownership Binding

On creation, the session is bound to the caller:

| User Type | Binding Fields |
|-----------|---------------|
| Authenticated | `user_id = get_current_user_id()` |
| Anonymous | `ip_address = inet_pton( wpaic_get_client_ip() )`, `user_agent_hash = hash( 'sha256', User-Agent )` |

### 5.6 Error Responses

| HTTP Status | Error Code | Message | Condition |
|-------------|-----------|---------|-----------|
| 404 | `wpaic_bot_not_found` | "No active chatbot configuration found." | Bot slug does not match any active bot |
| 429 | `rate_limit_exceeded` | "Too many requests. Please try again later." | Session creation rate limit exceeded |
| 500 | `wpaic_session_create_failed` | "Failed to create session." | Database insert failure |

### 5.7 Side Effects

- Inserts row into `wp_wpaic_sessions` with `status = 'active'`
- Fires action: `do_action( 'wpaic_session_created', $session_id, $session_token, $bot_id )`

---

## 6. REST Endpoint 4: Resume / Get Session

Retrieves session metadata and validates ownership. Used on page load when the frontend has a stored session token.

### 6.1 Route Definition

| Property | Value |
|----------|-------|
| URL | `/wp-json/wp-ai-chatbot/v1/sessions/{token}` |
| Method | GET |
| URL Parameter | `token` -- UUID v4 session token |
| Authentication | Ownership validation |
| Handler | `WPAIC_Session_Controller::get_session()` |

### 6.2 Authentication

| Requirement | Details |
|-------------|---------|
| Nonce | Optional `X-WP-Nonce` header for logged-in users. |
| Capability | None (public). Access controlled by ownership validation. |
| Ownership | `(token, user_id)` for authenticated; `(token, ip_address, user_agent_hash)` for anonymous. |
| Rate limiting | `wpaic_rate_limited_permission('session_get', 60, 60)`. |

Ref: AXI session validation by UUID format (lines 72-76), BAA history loading via `baachal_get_chat_history` action (line 64).

### 6.3 Request Schema

| Parameter | Location | Type | Required | Validation | Description |
|-----------|----------|------|----------|------------|-------------|
| `token` | URL path | string | Yes | UUID v4 regex: `/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i` | Session token |

### 6.4 Response Schema -- Success

HTTP Status: `200 OK`

```json
{
    "session_token": "550e8400-e29b-41d4-a716-446655440000",
    "bot_slug": "default",
    "bot_name": "AI Assistant",
    "status": "active",
    "message_count": 5,
    "created_at": "2026-03-01T10:30:00Z",
    "updated_at": "2026-03-01T11:15:00Z",
    "expires_at": "2026-03-02T11:15:00Z",
    "config": {
        "stream_enabled": true,
        "input_max_length": 512,
        "placeholder": "Write your question...",
        "suggestions_enabled": false,
        "start_sentence": null
    }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `session_token` | string | Echoed session token |
| `bot_slug` | string | Bot slug for this session |
| `bot_name` | string | Bot display name |
| `status` | string | One of: `"active"`, `"closed"`, `"expired"` |
| `message_count` | integer | Number of messages in session (denormalized from `wp_wpaic_sessions`) |
| `created_at` | string | ISO 8601 creation timestamp (UTC) |
| `updated_at` | string | ISO 8601 last-activity timestamp (UTC) |
| `expires_at` | string | ISO 8601 expiry timestamp (UTC) |
| `config` | object | Bot configuration for frontend rendering (same shape as create response) |

### 6.5 Error Responses

| HTTP Status | Error Code | Message | Condition |
|-------------|-----------|---------|-----------|
| 400 | `wpaic_invalid_session_token` | "Invalid session token format." | Token does not match UUID v4 regex |
| 403 | `wpaic_session_denied` | "Session access denied." | Ownership validation failure (generic, no detail) |
| 404 | `wpaic_session_not_found` | "Session not found." | No session with this token, or session is expired |
| 429 | `rate_limit_exceeded` | "Too many requests. Please try again later." | Rate limit exceeded |

### 6.6 Ownership Validation

The dual strategy from session 02:

- **Authenticated user** (`user_id > 0`): Match `session.user_id === get_current_user_id()`. IP is NOT checked (may change on mobile networks).
- **Anonymous user** (`user_id = 0`): Match `session.ip_address === inet_pton( wpaic_get_client_ip() )` AND `session.user_agent_hash === hash( 'sha256', HTTP_USER_AGENT )`.
- **Failure**: Return 403 with generic message `"Session access denied."` -- no details about which field mismatched (prevents enumeration attacks).

Ref: `session_model_contract.md` Section 4, session 02 `wp_wpaic_sessions` ownership columns (Section 2.3).

---

## 7. REST Endpoint 5: Close Session

Marks a session as closed. Triggered when the user clicks "New Conversation" or explicitly ends the chat. The session becomes read-only: no new messages can be sent, but history is still retrievable.

### 7.1 Route Definition

| Property | Value |
|----------|-------|
| URL | `/wp-json/wp-ai-chatbot/v1/sessions/{token}/close` |
| Method | POST |
| URL Parameter | `token` -- UUID v4 session token |
| Authentication | Ownership validation |
| Handler | `WPAIC_Session_Controller::close_session()` |

**Why POST instead of DELETE?** Closing is a state transition (`active -> closed`), not a resource deletion. The session record and its messages are preserved for history and analytics. `DELETE` is reserved for admin-only permanent deletion (Section 9).

### 7.2 Authentication

| Requirement | Details |
|-------------|---------|
| Nonce | Optional `X-WP-Nonce` header. |
| Capability | None (public). Ownership validated. |
| Ownership | Same dual strategy as Section 6.6. |
| Rate limiting | `wpaic_rate_limited_permission('session_close', 30, 60)`. |

Ref: BAA `wp_ajax_baachal_clear_chat_history` (line 66) -- clears history as session close equivalent.

### 7.3 Request Schema

| Parameter | Location | Type | Required | Validation | Description |
|-----------|----------|------|----------|------------|-------------|
| `token` | URL path | string | Yes | UUID v4 regex | Session token |

No request body required.

### 7.4 Response Schema -- Success

HTTP Status: `200 OK`

```json
{
    "session_token": "550e8400-e29b-41d4-a716-446655440000",
    "status": "closed",
    "closed_at": "2026-03-01T15:30:00Z"
}
```

| Field | Type | Description |
|-------|------|-------------|
| `session_token` | string | Echoed session token |
| `status` | string | Always `"closed"` |
| `closed_at` | string | ISO 8601 timestamp of the close operation (UTC) |

### 7.5 Error Responses

| HTTP Status | Error Code | Message | Condition |
|-------------|-----------|---------|-----------|
| 400 | `wpaic_invalid_session_token` | "Invalid session token format." | Invalid UUID format |
| 403 | `wpaic_session_denied` | "Session access denied." | Ownership validation failure |
| 404 | `wpaic_session_not_found` | "Session not found." | No session with this token |
| 409 | `wpaic_session_already_closed` | "Session is already closed." | Session status is `closed` or `expired` |
| 429 | `rate_limit_exceeded` | "Too many requests. Please try again later." | Rate limit exceeded |

### 7.6 Side Effects

- Updates `wp_wpaic_sessions` row: `SET status = 'closed', updated_at = NOW()`
- Fires action: `do_action( 'wpaic_session_closed', $session_id )`
- Session data and messages are retained per data retention policy (session 02 `data_retention_policy.md`)
- Frontend should clear the stored session token from localStorage

---

## 8. REST Endpoint 6: Get Session History

Retrieves the conversation history for a session. Used to restore the chat UI on page reload.

### 8.1 Route Definition

| Property | Value |
|----------|-------|
| URL | `/wp-json/wp-ai-chatbot/v1/sessions/{token}/history` |
| Method | GET |
| URL Parameter | `token` -- UUID v4 session token |
| Authentication | Ownership validation |
| Handler | `WPAIC_Session_Controller::get_history()` |

### 8.2 Authentication

| Requirement | Details |
|-------------|---------|
| Nonce | Optional `X-WP-Nonce` header. |
| Capability | None (public). Ownership validated. |
| Ownership | Same dual strategy as Section 6.6. |
| Rate limiting | `wpaic_rate_limited_permission('session_history', 60, 60)`. |

Ref: AXI `wp_ajax_aichat_get_history` / `wp_ajax_nopriv_aichat_get_history` (lines 41-42), BAA `wp_ajax_baachal_get_chat_history` / `wp_ajax_nopriv_baachal_get_chat_history` (lines 64-65).

### 8.3 Request Schema

| Parameter | Location | Type | Required | Validation | Default | Description |
|-----------|----------|------|----------|------------|---------|-------------|
| `token` | URL path | string | Yes | UUID v4 regex | -- | Session token |
| `limit` | Query string | integer | No | `absint()`, min 1, max 100 | `20` | Maximum messages to return |
| `before_id` | Query string | integer | No | `absint()`, positive | null | Cursor: return messages with `id < before_id` (load older) |
| `after_id` | Query string | integer | No | `absint()`, positive | null | Cursor: return messages with `id > after_id` (poll newer) |

### 8.4 Response Schema -- Success

HTTP Status: `200 OK`

```json
{
    "session_token": "550e8400-e29b-41d4-a716-446655440000",
    "messages": [
        {
            "id": 101,
            "role": "user",
            "message": "What is your return policy?",
            "response": "Our return policy allows returns within 30 days of purchase.",
            "created_at": "2026-03-01T10:31:00Z"
        },
        {
            "id": 102,
            "role": "user",
            "message": "How do I start a return?",
            "response": "To start a return, log in to your account and go to Order History.",
            "created_at": "2026-03-01T10:32:00Z"
        }
    ],
    "has_more": false,
    "total_count": 4
}
```

| Field | Type | Description |
|-------|------|-------------|
| `session_token` | string | Echoed session token |
| `messages` | array | Array of message exchange objects ordered by `id` ascending (chronological) |
| `messages[].id` | integer | Message row ID from `wp_wpaic_messages`; used as cursor for `before_id`/`after_id` pagination |
| `messages[].role` | string | Sender role: `"user"`, `"assistant"`, or `"system"` |
| `messages[].message` | string | User message or system prompt content |
| `messages[].response` | string or null | AI response text (sanitized HTML); null for system messages without a response |
| `messages[].created_at` | string | ISO 8601 message timestamp (UTC) |
| `has_more` | boolean | `true` if more messages exist beyond the returned set |
| `total_count` | integer | Total message count for the session |

**Note on paired rows**: The `wp_wpaic_messages` table stores user messages and AI responses as separate columns in the same row (`message` and `response`). This endpoint returns them as paired objects. The frontend reconstructs the conversation by rendering each row's `message` as a user bubble and `response` as an assistant bubble.

Ref: AXI history assembly `build_history_messages()` (lines 340-342), BAA `baachal_get_chat_history` AJAX action, session 02 `wp_wpaic_messages` with covering index `idx_session_id (session_id, id)`.

### 8.5 Error Responses

| HTTP Status | Error Code | Message | Condition |
|-------------|-----------|---------|-----------|
| 400 | `wpaic_invalid_session_token` | "Invalid session token format." | Invalid UUID format |
| 403 | `wpaic_session_denied` | "Session access denied." | Ownership validation failure |
| 404 | `wpaic_session_not_found` | "Session not found." | No session with this token |
| 429 | `rate_limit_exceeded` | "Too many requests. Please try again later." | Rate limit exceeded |

---

## 9. REST Endpoint 7: Delete Session (Admin Only)

Permanently deletes a session and all associated messages from the database. This is a destructive, irreversible operation restricted to administrators.

### 9.1 Route Definition

| Property | Value |
|----------|-------|
| URL | `/wp-json/wp-ai-chatbot/v1/sessions/{token}` |
| Method | DELETE |
| URL Parameter | `token` -- UUID v4 session token |
| Authentication | WordPress cookie + REST nonce + `manage_options` capability |
| Handler | `WPAIC_Session_Controller::delete_session()` |

### 9.2 Authentication

| Requirement | Details |
|-------------|---------|
| Nonce | Required. Header `X-WP-Nonce` with `wp_rest` value. |
| Capability | `manage_options` (WordPress administrator). Enforced in permission callback. |
| Rate limiting | `wpaic_rate_limited_permission('session_delete', 60, 60, function() { return current_user_can('manage_options'); })`. |

Ref: No direct equivalent in source plugins (AXI, MXC, BAA). Admin session deletion is a new WPAIC capability for data management and GDPR compliance.

### 9.3 Request Schema

| Parameter | Location | Type | Required | Validation | Description |
|-----------|----------|------|----------|------------|-------------|
| `token` | URL path | string | Yes | UUID v4 regex | Session token |

No request body required.

### 9.4 Response Schema -- Success

HTTP Status: `200 OK`

```json
{
    "deleted": true,
    "session_token": "550e8400-e29b-41d4-a716-446655440000",
    "messages_deleted": 12
}
```

| Field | Type | Description |
|-------|------|-------------|
| `deleted` | boolean | Always `true` on success |
| `session_token` | string | Echoed session token |
| `messages_deleted` | integer | Number of message rows deleted |

### 9.5 Error Responses

| HTTP Status | Error Code | Message | Condition |
|-------------|-----------|---------|-----------|
| 400 | `wpaic_invalid_session_token` | "Invalid session token format." | Invalid UUID format |
| 401 | `rest_not_logged_in` | "You must be logged in." | No authentication cookie |
| 403 | `rest_forbidden` | "You do not have permission to delete sessions." | User lacks `manage_options` capability |
| 404 | `wpaic_session_not_found` | "Session not found." | No session with this token |
| 429 | `rate_limit_exceeded` | "Too many requests. Please try again later." | Rate limit exceeded |

### 9.6 Behavior

Messages are deleted first (referential integrity), then the session row:

```php
$session_manager->delete( $session_id );
// DELETE FROM wp_wpaic_messages WHERE session_id = ?
// DELETE FROM wp_wpaic_sessions WHERE id = ?
```

Ref: `session_model_contract.md` Section 5.6.

---

## 10. Authentication Model

### 10.1 Nonce Strategy

Two nonce scopes are used:

| Scope | Action | Generation | Injection | Endpoints |
|-------|--------|-----------|-----------|-----------|
| Chat | `wpaic_chat` | `wp_create_nonce( 'wpaic_chat' )` | `wp_localize_script` into `wpaicChatData.nonce` | AJAX `wpaic_chat`, AJAX `wpaic_chat_stream` |
| REST | `wp_rest` | `wp_create_nonce( 'wp_rest' )` | `wp_localize_script` into `wpaicChatData.restNonce` | All REST API endpoints (via `X-WP-Nonce` header) |

**Nonce lifetime**: WordPress default -- 24 hours with a 12-hour grace period. Expired nonces return 403 with message "Security verification failed. Please refresh the page."

Ref: AXI nonce `aichat_ajax` via `wp_localize_script`, MXC nonce `mxchat_nonce` via localized object, BAA nonce `baachal_nonce` via localized object.

### 10.2 Frontend Configuration Injection

Both nonces are injected via `wp_localize_script()`:

```php
wp_localize_script( 'wpaic-chat', 'wpaicChatData', array(
    'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
    'restUrl'     => esc_url_raw( rest_url( 'wp-ai-chatbot/v1/' ) ),
    'nonce'       => wp_create_nonce( 'wpaic_chat' ),
    'restNonce'   => wp_create_nonce( 'wp_rest' ),
    'streamEnabled' => (bool) get_option( 'wpaic_runtime_stream_enabled', true ),
    'i18n' => array(
        'errorGeneric'    => __( 'Something went wrong. Please try again.', 'wp-ai-chatbot' ),
        'errorNonce'      => __( 'Session expired. Please refresh the page.', 'wp-ai-chatbot' ),
        'errorRateLimit'  => __( 'Too many messages. Please wait a moment.', 'wp-ai-chatbot' ),
        'errorUsageLimit' => __( 'Daily usage limit reached. Please try again tomorrow.', 'wp-ai-chatbot' ),
        'errorEmpty'      => __( 'Message cannot be empty.', 'wp-ai-chatbot' ),
        'errorTooLong'    => __( 'Message exceeds maximum length.', 'wp-ai-chatbot' ),
        'errorOffline'    => __( 'You appear to be offline. Please check your connection.', 'wp-ai-chatbot' ),
        'messageDuplicate' => __( 'Message already sent.', 'wp-ai-chatbot' ),
    ),
) );
```

The frontend uses `wpaicChatData.ajaxUrl` + `wpaicChatData.nonce` for AJAX endpoints (1, 2) and `wpaicChatData.restUrl` + `wpaicChatData.restNonce` for REST endpoints (3-7).

### 10.3 AJAX Nonce Verification

```php
$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wpaic_chat' ) ) {
    wp_send_json_error(
        array(
            'code'    => 'wpaic_nonce_failed',
            'message' => 'Security verification failed. Please refresh the page.',
        ),
        403
    );
}
```

Ref: AXI `wp_verify_nonce($nonce, 'aichat_ajax')` (line 55).

### 10.4 REST API Authentication

REST endpoints use WordPress built-in cookie authentication. When `X-WP-Nonce` header is present with a valid `wp_rest` nonce, WordPress sets `current_user`. Permission callbacks then check capabilities or delegate to ownership validation.

**Anonymous REST access**: Session endpoints (create, get, close, history) support anonymous access. The REST nonce is optional for these endpoints; ownership is validated via session token + IP + user-agent instead of WordPress user identity.

### 10.5 Capability Matrix

| Endpoint | Anonymous | Logged-In User | Admin (`manage_options`) |
|----------|-----------|---------------|------------------------|
| 1. Chat (sync) | Yes (nonce) | Yes (nonce) | Yes (nonce) |
| 2. Chat (stream) | Yes (nonce) | Yes (nonce) | Yes (nonce) |
| 3. Create session | Yes | Yes | Yes |
| 4. Get session | Yes (ownership) | Yes (ownership) | Yes (ownership) |
| 5. Close session | Yes (ownership) | Yes (ownership) | Yes (ownership) |
| 6. Get history | Yes (ownership) | Yes (ownership) | Yes (ownership) |
| 7. Delete session | No | No | Yes |

---

## 11. Error Response Format

### 11.1 Design Principle

All endpoints produce a consistent error structure regardless of transport. The frontend uses a single error handling path by normalizing both AJAX and REST error formats.

### 11.2 AJAX Error Format

```json
{
    "success": false,
    "data": {
        "code": "wpaic_error_code",
        "message": "User-friendly error description."
    }
}
```

Produced by `wp_send_json_error( array( 'code' => '...', 'message' => '...' ), $http_status )`.

### 11.3 REST Error Format

```json
{
    "code": "wpaic_session_not_found",
    "message": "Session not found.",
    "data": {
        "status": 404
    }
}
```

Produced by returning `new WP_Error( $code, $message, array( 'status' => $http_status ) )` from permission callbacks or handlers.

### 11.4 Frontend Normalization

```javascript
function normalizeError(response, isAjax) {
    if (isAjax) {
        return {
            code: response.data?.code || 'wpaic_unknown_error',
            message: response.data?.message || wpaicChatData.i18n.errorGeneric,
        };
    }
    return {
        code: response.code || 'wpaic_unknown_error',
        message: response.message || wpaicChatData.i18n.errorGeneric,
        status: response.data?.status || 500,
    };
}
```

### 11.5 Complete Error Code Registry

| Error Code | HTTP Status | Source Endpoint(s) | Description |
|-----------|-------------|-------------------|-------------|
| `wpaic_nonce_failed` | 403 | 1, 2 | WordPress nonce verification failed |
| `wpaic_empty_message` | 400 | 1, 2 | Message empty after sanitization |
| `wpaic_message_too_long` | 400 | 1, 2 | Message exceeds `bot.input_max_length` |
| `wpaic_invalid_session_token` | 400 | 1, 2, 4, 5, 6, 7 | Token does not match UUID v4 format |
| `wpaic_invalid_tool_results` | 400 | 1 | Tool continuation with malformed results |
| `wpaic_session_denied` | 403 | 1, 2, 4, 5, 6 | Ownership validation failed (generic message) |
| `wpaic_session_not_found` | 404 | 4, 5, 6, 7 | No session with the given token |
| `wpaic_session_already_closed` | 409 | 5 | Attempt to close a non-active session |
| `wpaic_session_create_failed` | 500 | 3 | Database insert failed during session creation |
| `wpaic_bot_not_found` | 404 | 1, 2, 3 | No active bot matching the slug |
| `wpaic_rate_limited` | 429 | 1, 2 | Chat request rate limit exceeded |
| `wpaic_usage_limited` | 429 | 1, 2 | Daily usage cap exceeded |
| `wpaic_provider_error` | 500 | 1, 2 | AI provider request failed |
| `wpaic_internal_error` | 500 | 1, 2 | Unhandled server-side exception |
| `rate_limit_exceeded` | 429 | 3, 4, 5, 6, 7 | REST endpoint rate limit (from `lib/rate-limiter.php`) |
| `rest_not_logged_in` | 401 | 7 | WordPress REST authentication required |
| `rest_forbidden` | 403 | 7 | Insufficient capability |

### 11.6 Error Message Guidelines

1. **No technical details**: Never expose stack traces, SQL errors, API keys, or internal file paths
2. **Actionable**: Tell the user what to do ("Please refresh the page", "Please try again")
3. **Consistent tone**: Neutral, professional language
4. **No provider leakage**: Never mention specific AI providers in user-facing messages; use generic "AI service" language
5. **i18n ready**: All messages wrapped in `__()` for translation

Ref: Session 03 `error_handling_fallback_contract.md` user_message guidelines, BAA contextual error messages.

---

## 12. Rate Limiting Integration

### 12.1 Architecture

Rate limiting integrates at two levels:

1. **AJAX endpoints**: Applied in the message lifecycle pipeline at step 1.7 (see `message_lifecycle_pipeline.md`). The check runs after nonce verification and before message validation.

2. **REST endpoints**: Applied via `wpaic_rate_limited_permission()` from `lib/rate-limiter.php` as each route's `permission_callback`.

### 12.2 Per-Endpoint Rate Limits

| Endpoint | Rate Limit Namespace | Default Max | Default Window | Source |
|----------|---------------------|-------------|----------------|--------|
| `wpaic_chat` (AJAX) | `chat` | `wpaic_security_rate_limit` option (default 60) | `wpaic_security_rate_window` option (default 60s) | Admin settings |
| `wpaic_chat_stream` (AJAX) | `chat` | Same as sync (shared namespace) | Same as sync | Shared budget |
| `POST /sessions` | `session_create` | 30 | 60s | Hardcoded |
| `GET /sessions/{token}` | `session_get` | 60 | 60s | Hardcoded |
| `POST /sessions/{token}/close` | `session_close` | 30 | 60s | Hardcoded |
| `GET /sessions/{token}/history` | `session_history` | 60 | 60s | Hardcoded |
| `DELETE /sessions/{token}` | `session_delete` | 60 | 60s | Hardcoded |

Streaming and sync chat share the `chat` rate limit namespace so users cannot bypass limits by switching between them.

### 12.3 AJAX Rate Limit Code

```php
// Pipeline step 1.7:
$rate_limit  = (int) get_option( 'wpaic_security_rate_limit', 60 );
$rate_window = (int) get_option( 'wpaic_security_rate_window', 60 );
$rate_result = wpaic_check_rate_limit( 'chat', $rate_limit, $rate_window );

$rate_result = apply_filters( 'wpaic_rate_limit_check', $rate_result, $session, $bot );

if ( ! $rate_result['allowed'] ) {
    wp_send_json_error(
        array(
            'code'    => 'wpaic_rate_limited',
            'message' => 'Too many requests. Please wait a moment.',
        ),
        429
    );
}
```

Ref: AXI `aichat_rate_limit_check()` (lines 150-157), `lib/rate-limiter.php`.

### 12.4 REST Rate Limit Code

```php
register_rest_route( 'wp-ai-chatbot/v1', '/sessions', array(
    'methods'             => 'POST',
    'callback'            => array( $this, 'create_session' ),
    'permission_callback' => wpaic_rate_limited_permission( 'session_create', 30, 60 ),
) );
```

The `wpaic_rate_limited_permission()` closure checks the rate limit and returns `WP_Error` with 429 status and `Retry-After` header if exceeded.

### 12.5 Rate Limit Response Headers

REST endpoints include informational rate limit headers (via `wpaic_add_rate_limit_headers()`):

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 42
Retry-After: 18     (only present when rate limited)
```

AJAX endpoints do NOT include rate limit headers (WordPress AJAX does not expose a response object for header manipulation before `wp_send_json_*`).

### 12.6 Rate Limit Hook

```php
$rate_result = apply_filters( 'wpaic_rate_limit_check', $rate_result, $session, $bot );
```

This filter (pipeline step 1.7) allows extensions to override rate limiting logic per session or bot. Return a `WP_Error` to block, or the unmodified `$rate_result` to allow.

---

## 13. REST Route Registration

### 13.1 Controller Class

```php
class WPAIC_Session_Controller extends WP_REST_Controller {

    protected $namespace = 'wp-ai-chatbot/v1';
    protected $rest_base = 'sessions';

    public function register_routes() {

        // Endpoint 3: Create session
        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_session' ),
                'permission_callback' => wpaic_rate_limited_permission( 'session_create', 30, 60 ),
                'args'                => $this->get_create_params(),
            ),
        ) );

        // Endpoint 4: Get session
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<token>[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_session' ),
                'permission_callback' => wpaic_rate_limited_permission( 'session_get', 60, 60 ),
            ),
            // Endpoint 7: Delete session (admin)
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_session' ),
                'permission_callback' => wpaic_rate_limited_permission(
                    'session_delete', 60, 60,
                    function () { return current_user_can( 'manage_options' ); }
                ),
            ),
        ) );

        // Endpoint 5: Close session
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<token>[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})/close', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'close_session' ),
                'permission_callback' => wpaic_rate_limited_permission( 'session_close', 30, 60 ),
            ),
        ) );

        // Endpoint 6: Get history
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<token>[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})/history', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_history' ),
                'permission_callback' => wpaic_rate_limited_permission( 'session_history', 60, 60 ),
                'args'                => $this->get_history_params(),
            ),
        ) );
    }
}
```

### 13.2 Parameter Definitions

```php
private function get_token_param() {
    return array(
        'description' => 'Session token (UUID v4).',
        'type'        => 'string',
        'required'    => true,
        'pattern'     => '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$',
    );
}

private function get_create_params() {
    return array(
        'bot_slug' => array(
            'description'       => 'Bot identifier slug.',
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_title',
        ),
        'page_id' => array(
            'description' => 'WordPress page/post ID where the session originates.',
            'type'        => 'integer',
            'minimum'     => 1,
        ),
    );
}

private function get_history_params() {
    return array(
        'token' => $this->get_token_param(),
        'limit' => array(
            'description' => 'Maximum messages to return.',
            'type'        => 'integer',
            'default'     => 20,
            'minimum'     => 1,
            'maximum'     => 100,
        ),
        'before_id' => array(
            'description' => 'Return messages with ID less than this value (cursor for older messages).',
            'type'        => 'integer',
            'minimum'     => 1,
        ),
        'after_id' => array(
            'description' => 'Return messages with ID greater than this value (cursor for newer messages).',
            'type'        => 'integer',
            'minimum'     => 1,
        ),
    );
}
```

### 13.3 Permission Callbacks

```php
/**
 * Validate session ownership in the handler (not permission callback).
 * Permission callback handles rate limiting; ownership is checked in the handler
 * because it requires loading the session from the database.
 */
private function validate_session_ownership( $token ) {
    if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $token ) ) {
        return new WP_Error( 'wpaic_invalid_session_token', 'Invalid session token format.', array( 'status' => 400 ) );
    }

    $session = $this->session_manager->get_by_token( $token );
    if ( ! $session ) {
        return new WP_Error( 'wpaic_session_not_found', 'Session not found.', array( 'status' => 404 ) );
    }

    $context = $this->build_request_context();
    if ( ! $this->session_manager->validate_ownership( $session, $context ) ) {
        return new WP_Error( 'wpaic_session_denied', 'Session access denied.', array( 'status' => 403 ) );
    }

    return $session;
}
```

---

## 14. Source Comparison Summary

| Aspect | AxiaChat | MxChat | Baachal | WPAIC |
|--------|----------|--------|---------|-------|
| Chat transport | AJAX only | AJAX (sync + stream) | AJAX only | AJAX (sync + stream) |
| AJAX action (chat) | `aichat_process_message` | `mxchat_handle_chat_request` | `baachal_message` | `wpaic_chat` |
| AJAX action (stream) | N/A | `mxchat_stream_chat` | N/A | `wpaic_chat_stream` |
| Session management | Implicit (in chat handler) | Implicit (cookie-based) | AJAX (`get_history`, `clear_history`) | REST API (CRUD) |
| Nonce action | `aichat_ajax` | `mxchat_nonce` | `baachal_nonce` | `wpaic_chat` (AJAX), `wp_rest` (REST) |
| Nonce injection | `wp_localize_script` | `wp_localize_script` | `wp_localize_script` | `wp_localize_script` |
| History endpoint | AJAX `aichat_get_history` | N/A (client-side) | AJAX `baachal_get_chat_history` | REST `GET /sessions/{token}/history` |
| Session close | N/A | N/A | AJAX `baachal_clear_chat_history` | REST `POST /sessions/{token}/close` |
| Session delete | N/A (via admin page) | N/A | N/A | REST `DELETE /sessions/{token}` (admin) |
| Error format | `wp_send_json_error()` | `wp_send_json_error()` | `wp_send_json_error()` | `wp_send_json_error()` (AJAX), `WP_Error` (REST) |
| Tool continuation | `handle_tool_continuation()` | N/A | N/A | `continue_tool` parameter in chat endpoint |
| Rate limiting | Custom per-IP check | N/A server-side | N/A | `lib/rate-limiter.php` transient-based |
| Admin-only endpoint | N/A | N/A | N/A | `DELETE` with `manage_options` |
| Anonymous access | `wp_ajax_nopriv_` | `wp_ajax_nopriv_` | `wp_ajax_nopriv_` | `wp_ajax_nopriv_` + REST public |

---

## 15. Testability Notes

### 15.1 Unit Testable

- Nonce verification (mock `wp_verify_nonce`)
- Input sanitization and validation (each field independently)
- UUID v4 regex validation
- Ownership validation logic (authenticated vs anonymous paths)
- Error response formatting (code, message, HTTP status)
- Request parameter parsing and defaults
- Rate limit integration (mock `wpaic_check_rate_limit`)
- History response transformation (DB rows to chronological message array)

### 15.2 Integration Testable

- Full AJAX chat request cycle (mock provider, verify response structure)
- Full AJAX streaming cycle (mock streaming provider, verify SSE chunk sequence)
- REST session CRUD lifecycle (create -> get -> close -> get returns closed)
- REST history pagination (before_id cursor, limit, has_more)
- REST admin delete with capability check (admin succeeds, non-admin 403)
- Rate limiting across multiple requests (transient state accumulation)
- Ownership validation on REST session endpoints
- Tool continuation two-phase flow (sync endpoint)

### 15.3 End-to-End Testable

- Frontend sends AJAX -> receives response -> updates DOM
- Frontend opens SSE stream -> renders chunks -> handles done
- Session token persistence in localStorage -> resume on page reload
- Error display in chat widget for each error code

### 15.4 Mock Points

| Dependency | Mock Strategy |
|-----------|--------------|
| `wp_verify_nonce()` | Stub to return true/false |
| `$wpdb` | In-memory array (per existing `tests/php/stubs/` pattern) |
| Provider adapter | Mock `chat()` / `stream_chat()` returning fixed responses |
| `get_option()` | Stub returning test configuration values |
| `get_transient()` / `set_transient()` | In-memory transient store |
| `current_user_can()` | Stub to control capability checks |
| `wpaic_get_client_ip()` | Return fixed IP for ownership tests |

### 15.5 Edge Cases

- Anonymous user on mobile network where IP changes between session creation and subsequent request (ownership validation fails for anonymous; succeeds for authenticated via user_id)
- Concurrent messages on same session (race condition on `message_count` increment -- handled by atomic SQL `SET col = col + 1`)
- Streaming endpoint receives non-streaming provider (falls back to JSON response)
- Session token passes UUID v4 regex but does not exist in database (returns 404)
- History `before_id` pointing to a message in a different session (ignored; query scoped to session)
- Admin DELETE on already-deleted session (returns 404, idempotent)
- Nonce expired after 24 hours (returns 403 with refresh guidance)
- Close on already-closed session (returns 409)

---
