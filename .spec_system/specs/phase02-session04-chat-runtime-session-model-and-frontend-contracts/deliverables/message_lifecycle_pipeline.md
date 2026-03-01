# Message Lifecycle Pipeline

**Session**: `phase02-session04-chat-runtime-session-model-and-frontend-contracts`
**Version**: 1.0
**Created**: 2026-03-01
**Module**: `includes/runtime/class-message-pipeline.php`

---

## 1. Overview

The message lifecycle pipeline defines the complete processing sequence for every chat message, from user input to rendered response. It follows a linear multi-stage architecture with WordPress hooks at each stage for extensibility.

The design combines AxiaChat's multi-stage validated pipeline (pre-processing chain with early-exit on validation failure) with Baachal's hook-driven extensibility (filterable at every stage). MxChat contributes the page context extraction pattern integrated as a pre-processing stage.

### Reference Anchors

| Source Plugin | File | Lines | Relevance |
|---------------|------|-------|-----------|
| AxiaChat | `axiachat-ai/includes/class-aichat-ajax.php` | 1-800 | Primary: full pipeline pattern (validation, dispatch, post-processing, tool continuation) |
| AxiaChat | `axiachat-ai/includes/class-aichat-ajax.php` | 131-200 | Honeypot, captcha, rate limit, spam signature, moderation pipeline |
| Baachal | `baachal/baachal.php` | 57-87 | Hook-driven registration via `apply_filters('baachal_register_hooks', ...)` |
| MxChat | `mxchat-basic/includes/class-mxchat-public.php` | N/A | No server-side message pipeline in public class |

---

## 2. Pipeline Architecture

### 2.1 Macro-Stage Overview

```
USER INPUT
    |
    v
+---------------------------+
| STAGE 1: PRE-PROCESSING   |
| (Validation & Security)   |
+---------------------------+
    |  [early-exit on failure]
    v
+---------------------------+
| STAGE 2: CONTEXT ASSEMBLY |
| (Session + RAG + Page)    |
+---------------------------+
    |
    v
+---------------------------+
| STAGE 3: PROVIDER DISPATCH|
| (Message build + AI call) |
+---------------------------+
    |
    v
+---------------------------+
| STAGE 4: POST-PROCESSING  |
| (Sanitize + Log + Render) |
+---------------------------+
    |
    v
RESPONSE OUTPUT
```

### 2.2 Design Principles

- **Sequential execution**: Stages run in strict order; no parallelism within a single request
- **Early exit**: Any validation failure in pre-processing terminates the pipeline with an error response
- **Hook-driven**: WordPress action/filter hooks at each stage allow extension without modifying core code
- **Stateless between requests**: Each request is independent; session state lives in the database
- **Error propagation**: Errors at any stage produce a structured error response to the frontend

---

## 3. Stage 1: Pre-Processing

Pre-processing validates the request, resolves the session, and runs security checks. All checks execute in the order listed; failure at any step returns an error response immediately.

### 3.1 Stage Sequence

| # | Step | Hook | Early Exit | Reference |
|---|------|------|-----------|-----------|
| 1.1 | Nonce verification | - | 403 error | AXI line 55 `wp_verify_nonce` |
| 1.2 | Input sanitization | - | 400 error | AXI lines 72-76 |
| 1.3 | Bot resolution | `wpaic_resolve_bot` (filter) | 404 error | AXI `resolve_bot()` method |
| 1.4 | Session resolution | `wpaic_resolve_session` (filter) | 403 error | AXI lines 55-80 UUID generation |
| 1.5 | Ownership validation | - | 403 error | Session 02 ownership schema |
| 1.6 | Honeypot check | - | 403 silent | AXI lines 142-148 |
| 1.7 | Rate limiting | `wpaic_rate_limit_check` (filter) | 429 error | `lib/rate-limiter.php` |
| 1.8 | Message validation | `wpaic_validate_message` (filter) | 400 error | AXI lines 159-164 |
| 1.9 | Duplicate detection | - | 200 duplicate flag | AXI lines 166-184 |
| 1.10 | Moderation | `wpaic_moderate_message` (filter) | 200 moderated flag | AXI lines 186-200 |
| 1.11 | Usage limit check | - | 429 error | Session 02 `wpaic_security_usage_limits_enabled` |

### 3.2 Step Details

#### 1.1 Nonce Verification

```php
// Verify WordPress nonce
$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wpaic_chat' ) ) {
    // Return 403 error
}
```

**Nonce action**: `wpaic_chat` (single action for all chat endpoints).
**No hook**: Nonce verification is non-negotiable and cannot be filtered.

#### 1.2 Input Sanitization

All request fields are sanitized before any business logic:

| Field | Sanitizer | Type |
|-------|-----------|------|
| `nonce` | `sanitize_text_field()` | string |
| `message` | `sanitize_textarea_field()` | string |
| `bot_slug` | `sanitize_title()` | string |
| `session_token` | Custom UUID validator | string |
| `page_context` | `wp_kses()` with empty allowlist | JSON string |

**Session token validation**: Must match `/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i` (UUID v4 format). Invalid tokens are rejected; empty tokens trigger new session creation.

#### 1.3 Bot Resolution

Resolve the target bot from the request:

**Priority order**:
1. `bot_slug` from request (explicit shortcode/widget selection)
2. Page meta `_wpaic_bot_slug` (per-page override)
3. Global option `wpaic_admin_global_bot_slug` (site-wide default)
4. First active bot in `wp_wpaic_bots` ordered by `id ASC`

**Hook**: `wpaic_resolve_bot` (filter)
```php
$bot = apply_filters( 'wpaic_resolve_bot', $bot, $bot_slug, $request_context );
// $bot: array (bot row from wp_wpaic_bots) or null
// $bot_slug: string (requested slug)
// $request_context: array (page_id, user_id, etc.)
```

**Error**: If no active bot is found, return 404 error: `"No active chatbot configuration found."`

Ref: AXI `resolve_bot()` method, MXC `mxchat_get_bot_options` filter, BAA single-bot via options.

#### 1.4 Session Resolution

Resolve or create the chat session:

- **Existing token**: Look up `wp_wpaic_sessions` by `session_token` where `status = 'active'`
- **No token / expired**: Create new session row with UUID v4 token via `wp_generate_uuid4()`
- **Token format**: UUID v4 (lowercase hex with dashes, 36 characters)

**Hook**: `wpaic_resolve_session` (filter)
```php
$session = apply_filters( 'wpaic_resolve_session', $session, $session_token, $bot, $request_context );
```

**On creation**: Fire action `wpaic_session_created( $session_id, $session_token, $bot_id )`

Ref: AXI lines 55-80 UUID generation, MXC cookie-based per-bot sessions, BAA localStorage sessions.

#### 1.5 Ownership Validation

Verify the caller owns the session:

- **Authenticated user**: Match `(session_token, user_id)` -- IP may change on mobile networks
- **Anonymous user**: Match `(session_token, ip_address, user_agent_hash)`
- **IP retrieval**: `wpaic_get_client_ip()` checks `HTTP_X_FORWARDED_FOR`, `HTTP_X_REAL_IP`, `REMOTE_ADDR` in order (proxy-aware, matching `lib/rate-limiter.php`)
- **User-agent hash**: `hash( 'sha256', $_SERVER['HTTP_USER_AGENT'] ?? '' )`

**Failure**: Return 403 error: `"Session access denied."` -- no details about which field mismatched (security).

Ref: Session 02 `wp_wpaic_sessions` ownership columns.

#### 1.6 Honeypot Check

```php
$honeypot = $_POST['wpaic_hp'] ?? '';
if ( ! empty( $honeypot ) ) {
    // Silently return success with empty message (don't reveal detection)
}
```

**HTML contract**: Widget renders `<input type="text" name="wpaic_hp" style="display:none" tabindex="-1" autocomplete="off">`. Bots fill this; humans don't.

**No hook**: Honeypot is a fixed security mechanism.

Ref: AXI lines 142-148 `aichat_hp` field.

#### 1.7 Rate Limiting

Integrates with `lib/rate-limiter.php`:

```php
$rate_result = wpaic_check_rate_limit( 'chat', wpaic_get_client_ip() );
// Uses transient: wpaic_rate_chat_{ip_hash}
// Default: 60 requests / 60 seconds (configurable via wpaic_security_rate_limit / wpaic_security_rate_window)
```

**Hook**: `wpaic_rate_limit_check` (filter)
```php
$rate_result = apply_filters( 'wpaic_rate_limit_check', $rate_result, $session, $bot );
// Return WP_Error to block, null to allow
```

**Error**: Return 429 error with `Retry-After` header.

Ref: AXI lines 150-157 `aichat_rate_limit_check()`, `lib/rate-limiter.php` transient-based limiter.

#### 1.8 Message Validation

| Check | Rule | Error |
|-------|------|-------|
| Empty message | `trim( $message ) === ''` | 400: "Message cannot be empty." |
| Max length | `mb_strlen( $message ) > $bot['input_max_length']` (default 512, absolute max 4000) | 400: "Message exceeds maximum length." |

**Hook**: `wpaic_validate_message` (filter)
```php
$validation = apply_filters( 'wpaic_validate_message', null, $message, $bot, $session );
// Return WP_Error to reject, null to allow
```

Ref: AXI lines 159-164 (hard max 4000 chars).

#### 1.9 Duplicate Detection

Prevent identical messages submitted in rapid succession:

```php
$message_hash = md5( $session_token . $message );
$transient_key = 'wpaic_dup_' . $message_hash;
if ( false !== get_transient( $transient_key ) ) {
    // Return success with duplicate flag (not error)
    wp_send_json_success( array( 'duplicate' => true ) );
}
set_transient( $transient_key, 1, 10 ); // 10-second window
```

**Response**: Success response with `duplicate: true` flag (frontend shows "Message already sent" without error styling).

Ref: AXI lines 166-184 `aichat_spam_signature_check()` with `aichat_dup` code.

#### 1.10 Moderation

Content moderation check before provider dispatch:

**Hook**: `wpaic_moderate_message` (filter)
```php
$moderation = apply_filters( 'wpaic_moderate_message', null, $message, $bot, $session );
// Return WP_Error to reject, null to allow
```

**Built-in checks** (when `wpaic_security_moderation_enabled` option is true):
1. Banned word list check against `wpaic_security_banned_words` option
2. External moderation API call (if configured, future session 06 scope)

**Response on rejection**: Success response with `moderated: true` and customizable rejection message from `wpaic_security_rejection_message` option.

**Soft-fail**: Moderation failures return a "moderated" success response, not a hard error. The user sees a polite rejection message in the chat UI.

Ref: AXI lines 186-200 `aichat_run_moderation_checks()`.

#### 1.11 Usage Limit Check

When `wpaic_security_usage_limits_enabled` is true:

- Check daily message count against `wpaic_security_max_daily_messages`
- Check daily token usage against `wpaic_security_max_daily_tokens`
- Check daily cost against `wpaic_security_max_daily_cost`
- Scope: per-user (authenticated) or per-IP (anonymous)

**Error**: Return 429 error: `"Daily usage limit reached. Please try again tomorrow."`

Ref: Session 02 options schema `wpaic_security_usage_limits_enabled`, `wpaic_security_max_daily_*`.

---

## 4. Stage 2: Context Assembly

After pre-processing passes, assemble the context needed for the provider request.

### 4.1 Stage Sequence

| # | Step | Hook | Reference |
|---|------|------|-----------|
| 2.1 | History retrieval | `wpaic_history_messages` (filter) | AXI lines 340-342 |
| 2.2 | Page context injection | `wpaic_inject_page_context` (filter) | MXC lines 208-311 |
| 2.3 | RAG context retrieval | `wpaic_rag_context` (filter) | Session 05 scope |
| 2.4 | System prompt assembly | `wpaic_system_prompt` (filter) | AXI system prompt building |
| 2.5 | Message array construction | `wpaic_messages_before_provider` (filter) | AXI `aichat_messages_before_provider` |

### 4.2 Step Details

#### 2.1 History Retrieval

```php
$history = wpaic_get_session_history( $session_id, $bot['max_messages'] );
// Returns array of [role, content] pairs from wp_wpaic_messages
// Ordered by id ASC (chronological)
// Limited to $bot['max_messages'] most recent exchanges
```

**Hook**: `wpaic_history_messages` (filter)
```php
$history = apply_filters( 'wpaic_history_messages', $history, $session, $bot );
```

Ref: AXI lines 340-342 `build_history_messages()`, session 02 `wp_wpaic_messages` schema.

#### 2.2 Page Context Injection (CFI-RUN-001)

Full specification in `page_context_injection.md`. Summary:

- Frontend sends `page_context` JSON in the request (URL, title, content excerpt)
- Pipeline injects it into the system prompt or as a user context prefix
- Controlled by `$bot['context_mode']` ('page', 'embeddings', 'none')
- Maximum 3000 characters of page content

**Hook**: `wpaic_inject_page_context` (filter)
```php
$page_context = apply_filters( 'wpaic_inject_page_context', $page_context, $bot, $session );
```

Ref: MXC lines 208-311 `getPageContext()`, AXI lines 297-305 `page_id` context mode.

#### 2.3 RAG Context Retrieval

Placeholder for session 05 (RAG pipeline). The hook is defined here for pipeline completeness:

**Hook**: `wpaic_rag_context` (filter)
```php
$rag_context = apply_filters( 'wpaic_rag_context', array(), $message, $bot, $session );
// Returns array of context chunks with title, content, score
```

#### 2.4 System Prompt Assembly

Build the system prompt from bot instructions + injected context:

```php
$system_prompt = $bot['instructions'] ?? '';

// Inject datetime context (if enabled)
if ( get_option( 'wpaic_runtime_datetime_injection', false ) ) {
    $system_prompt .= "\n\nCurrent date and time: " . current_time( 'Y-m-d H:i:s T' );
}

// Inject user context (if enabled and user is authenticated)
if ( get_option( 'wpaic_runtime_user_context_injection', false ) && $session['user_id'] > 0 ) {
    $user = get_userdata( $session['user_id'] );
    $system_prompt .= "\n\nUser: " . $user->display_name;
}

// Inject page context
if ( ! empty( $page_context ) ) {
    $system_prompt .= "\n\n--- Page Context ---\n" . $page_context;
}

// Inject RAG context
if ( ! empty( $rag_context ) ) {
    $system_prompt .= "\n\n--- Knowledge Context ---\n";
    foreach ( $rag_context as $chunk ) {
        $system_prompt .= "\n[" . $chunk['title'] . "]\n" . $chunk['content'] . "\n";
    }
}

// Inject suggestion instructions (if enabled)
if ( $bot['ui_suggestions_enabled'] ) {
    $system_prompt .= "\n\n" . wpaic_get_suggestions_prompt( $bot['ui_suggestions_count'] );
}
```

**Hook**: `wpaic_system_prompt` (filter)
```php
$system_prompt = apply_filters( 'wpaic_system_prompt', $system_prompt, $bot, $session, $context );
```

#### 2.5 Message Array Construction

Build the final messages array for the provider:

```php
$messages = array();

// System message
$messages[] = array( 'role' => 'system', 'content' => $system_prompt );

// History messages
foreach ( $history as $exchange ) {
    $messages[] = array( 'role' => 'user', 'content' => $exchange['message'] );
    if ( ! empty( $exchange['response'] ) ) {
        $messages[] = array( 'role' => 'assistant', 'content' => $exchange['response'] );
    }
}

// Current user message
$messages[] = array( 'role' => 'user', 'content' => $message );
```

**Hook**: `wpaic_messages_before_provider` (filter)
```php
$messages = apply_filters( 'wpaic_messages_before_provider', $messages, $bot, $session );
```

**Intercept hook**: `wpaic_intercept_request` (filter)
```php
$intercept = apply_filters( 'wpaic_intercept_request', null, $messages, $bot, $session );
// Return non-null to skip provider dispatch entirely (e.g., LiveChat handoff)
```

Ref: AXI `aichat_messages_before_provider`, `aichat_maybe_intercept_request`.

---

## 5. Stage 3: Provider Dispatch

Invoke the AI provider with the assembled message payload.

### 5.1 Stage Sequence

| # | Step | Hook | Reference |
|---|------|------|-----------|
| 3.1 | Provider resolution | `wpaic_resolve_provider` (filter) | Session 03 registry |
| 3.2 | Parameter assembly | `wpaic_provider_params` (filter) | Session 03 chat payload |
| 3.3 | Streaming decision | - | `wpaic_runtime_stream_enabled` option |
| 3.4a | Sync dispatch | `wpaic_after_provider_request` (action) | Session 03 `Provider_Interface::chat()` |
| 3.4b | Stream dispatch | `wpaic_after_provider_request` (action) | Session 03 `Streamable_Provider_Interface::stream_chat()` |
| 3.5 | Tool continuation | - | AXI tool continuation pattern |

### 5.2 Step Details

#### 3.1 Provider Resolution

```php
$provider_id = $bot['provider'];  // e.g., 'openai', 'claude', 'gemini'
$provider = wpaic_get_provider( $provider_id );
```

**Hook**: `wpaic_resolve_provider` (filter)
```php
$provider = apply_filters( 'wpaic_resolve_provider', $provider, $provider_id, $bot );
```

Ref: Session 03 `provider_registry_dispatcher.md`.

#### 3.2 Parameter Assembly

```php
$params = array(
    'model'       => $bot['model'],
    'temperature' => (float) $bot['temperature'],
    'max_tokens'  => (int) $bot['max_tokens'],
    'stream'      => $use_streaming,
    'metadata'    => array(
        'request_id'  => wp_generate_uuid4(),
        'session_id'  => $session['session_token'],
        'bot_slug'    => $bot['slug'],
        'user_id'     => $session['user_id'],
    ),
);
```

**Hook**: `wpaic_provider_params` (filter)
```php
$params = apply_filters( 'wpaic_provider_params', $params, $bot, $session, $messages );
```

Ref: Session 03 `chat_payload_schemas.md`.

#### 3.3 Streaming Decision

```php
$stream_enabled = (bool) get_option( 'wpaic_runtime_stream_enabled', false );
$supports_streaming = ( $provider instanceof Streamable_Provider_Interface )
    && $provider->supports_streaming();
$use_streaming = $stream_enabled && $supports_streaming;
```

When streaming is used, the pipeline switches to SSE output mode. See `streaming_delivery_contract.md` for the full streaming flow.

#### 3.4a Sync Dispatch

```php
$result = $provider->chat( $messages, $params );
// Returns: array with message, usage, finish_reason, tool_calls, provider_id, model_id, request_id
// Or: array with error key on failure
```

#### 3.4b Stream Dispatch

```php
// Runtime sets SSE headers and flushes output buffers first
// Then invokes streaming with a callback
$callback = function( $chunk ) {
    echo "data: " . wp_json_encode( $chunk ) . "\n\n";
    if ( ob_get_level() ) {
        ob_flush();
    }
    flush();
};

$result = $provider->stream_chat( $messages, $params, $callback );
```

Ref: Session 03 `streaming_transport_layer.md` for chunk format and lifecycle.

#### 3.4 Post-Dispatch Action

```php
do_action( 'wpaic_after_provider_request', $result, $params, $bot, $session );
```

Fires regardless of sync/stream mode. Used by cost tracking (session 03 `cost_accounting_interface.md`).

#### 3.5 Tool Continuation

When `$result['finish_reason'] === 'tool_calls'`:

**Phase 1**: Return tool-pending response to frontend
```php
wp_send_json_success( array(
    'status'      => 'tool_pending',
    'response_id' => $result['request_id'],
    'tool_calls'  => $result['tool_calls'],
    'session_id'  => $session['session_token'],
    'bot_slug'    => $bot['slug'],
    'model'       => $params['model'],
) );
```

**Phase 2**: Frontend executes tools (or auto-continues) and sends continuation request:
```
POST /wp-admin/admin-ajax.php
action=wpaic_chat
continue_tool=1
response_id={uuid}
tool_calls=[{call_id, name, result}]
session_token={token}
bot_slug={slug}
```

The continuation request re-enters the pipeline at Stage 3 (skipping pre-processing validation, re-using the existing session), assembles tool result messages, and dispatches to the provider again.

Ref: AXI tool continuation pattern (lines 100-127, `process_tool_continuation()`).

---

## 6. Stage 4: Post-Processing

Process the provider response before returning to the frontend.

### 6.1 Stage Sequence

| # | Step | Hook | Reference |
|---|------|------|-----------|
| 4.1 | Error handling | `wpaic_provider_error` (filter) | Session 03 error contract |
| 4.2 | Response sanitization | `wpaic_sanitize_response` (filter) | AXI `sanitize_answer_html()` |
| 4.3 | Suggestion extraction | - | AXI `extract_suggestions_from_answer()` |
| 4.4 | Cost calculation | - | Session 03 cost accounting |
| 4.5 | Conversation logging | `wpaic_before_log_message` (action) | AXI `maybe_log_conversation()` |
| 4.6 | Session aggregates update | - | Session 02 token tracking |
| 4.7 | Response assembly | `wpaic_response_data` (filter) | AXI `aichat_response_data` |

### 6.2 Step Details

#### 4.1 Error Handling

If `$result` contains an `error` key:

```php
if ( isset( $result['error'] ) ) {
    $error = $result['error'];
    $error = apply_filters( 'wpaic_provider_error', $error, $bot, $session );
    $user_message = wpaic_get_user_error_message( $error['code'], $error['type'] );
    wp_send_json_error( array( 'message' => $user_message ), $error['code'] ?: 500 );
}
```

**Hook**: `wpaic_provider_error` (filter) -- allows error transformation (e.g., custom messages per provider).

Ref: Session 03 `error_handling_fallback_contract.md`.

#### 4.2 Response Sanitization

Server-side sanitization of the AI response before storage and return:

```php
$response_text = $result['message'];
$response_text = apply_filters( 'wpaic_sanitize_response', $response_text, $bot, $session );
```

Built-in sanitization:
- Strip suggestion markers (see 4.3)
- Apply `wp_kses()` with explicit HTML allowlist (see `input_output_safety.md`)
- Convert markdown to HTML if enabled via `wpaic_runtime_markdown_enabled` option

Ref: AXI `sanitize_answer_html()`, `maybe_render_markdown_answer()`.

#### 4.3 Suggestion Extraction

Extract suggestion chips from the response if the bot has suggestions enabled:

```php
// Pattern: [[WPAIC_SUGGESTIONS]]{"suggestions":["Q1","Q2","Q3"]}[[/WPAIC_SUGGESTIONS]]
$suggestions = wpaic_extract_suggestions( $response_text );
// Returns: array of strings (sanitized, deduped, URL-free)
// Side effect: removes marker from $response_text
```

Ref: AXI `extract_suggestions_from_answer()`.

#### 4.4 Cost Calculation

```php
$cost_micros = null;
if ( ! empty( $result['usage'] ) ) {
    $cost_micros = $provider->calculate_cost( $result['usage'], $params['model'] );
}
```

Ref: Session 03 `cost_accounting_interface.md`.

#### 4.5 Conversation Logging

Persist the exchange to `wp_wpaic_messages`:

```php
do_action( 'wpaic_before_log_message', $message, $response_text, $session, $bot );

if ( $bot['history_persistence'] ) {
    $wpdb->insert( $messages_table, array(
        'session_id'        => $session['id'],
        'role'              => 'user',
        'message'           => $message,
        'response'          => $response_text,
        'provider'          => $result['provider_id'] ?? $bot['provider'],
        'model'             => $result['model_id'] ?? $bot['model'],
        'prompt_tokens'     => $result['usage']['prompt_tokens'] ?? null,
        'completion_tokens' => $result['usage']['completion_tokens'] ?? null,
        'cost_micros'       => $cost_micros,
        'rag_context'       => ! empty( $rag_context ) ? wp_json_encode( $rag_context ) : null,
    ) );
}
```

Ref: AXI `maybe_log_conversation()`, session 02 `wp_wpaic_messages` schema.

#### 4.6 Session Aggregates Update

```php
$wpdb->query( $wpdb->prepare(
    "UPDATE {$sessions_table} SET
        total_prompt_tokens = total_prompt_tokens + %d,
        total_completion_tokens = total_completion_tokens + %d,
        total_cost_micros = total_cost_micros + %d,
        message_count = message_count + 1,
        updated_at = %s
    WHERE id = %d",
    $result['usage']['prompt_tokens'] ?? 0,
    $result['usage']['completion_tokens'] ?? 0,
    $cost_micros ?? 0,
    current_time( 'mysql' ),
    $session['id']
) );
```

Ref: Session 02 `wp_wpaic_sessions` token tracking pattern.

#### 4.7 Response Assembly

```php
$response_data = array(
    'message'     => $response_text,
    'suggestions' => $suggestions,
);

$response_data = apply_filters( 'wpaic_response_data', $response_data, $result, $bot, $session );

wp_send_json_success( $response_data );
```

**Hook**: `wpaic_response_data` (filter) -- allows injecting debug info, metadata, etc.

Ref: AXI `aichat_response_data`.

---

## 7. Complete Hook Registry

### 7.1 Filters

| Hook Name | Stage | Arguments | Return | Purpose |
|-----------|-------|-----------|--------|---------|
| `wpaic_resolve_bot` | 1.3 | `$bot, $slug, $ctx` | `array\|null` | Override bot resolution |
| `wpaic_resolve_session` | 1.4 | `$session, $token, $bot, $ctx` | `array` | Override session resolution |
| `wpaic_rate_limit_check` | 1.7 | `$result, $session, $bot` | `WP_Error\|null` | Custom rate limiting |
| `wpaic_validate_message` | 1.8 | `$error, $message, $bot, $session` | `WP_Error\|null` | Custom message validation |
| `wpaic_moderate_message` | 1.10 | `$error, $message, $bot, $session` | `WP_Error\|null` | Content moderation |
| `wpaic_history_messages` | 2.1 | `$history, $session, $bot` | `array` | Modify history |
| `wpaic_inject_page_context` | 2.2 | `$context, $bot, $session` | `string\|null` | Modify page context |
| `wpaic_rag_context` | 2.3 | `$chunks, $message, $bot, $session` | `array` | RAG context retrieval |
| `wpaic_system_prompt` | 2.4 | `$prompt, $bot, $session, $ctx` | `string` | Modify system prompt |
| `wpaic_messages_before_provider` | 2.5 | `$messages, $bot, $session` | `array` | Modify message array |
| `wpaic_intercept_request` | 2.5 | `$result, $messages, $bot, $session` | `array\|null` | Skip provider dispatch |
| `wpaic_resolve_provider` | 3.1 | `$provider, $id, $bot` | `object` | Override provider |
| `wpaic_provider_params` | 3.2 | `$params, $bot, $session, $messages` | `array` | Modify provider params |
| `wpaic_provider_error` | 4.1 | `$error, $bot, $session` | `array` | Transform errors |
| `wpaic_sanitize_response` | 4.2 | `$text, $bot, $session` | `string` | Modify response text |
| `wpaic_response_data` | 4.7 | `$data, $result, $bot, $session` | `array` | Modify final response |

### 7.2 Actions

| Hook Name | Stage | Arguments | Purpose |
|-----------|-------|-----------|---------|
| `wpaic_session_created` | 1.4 | `$session_id, $token, $bot_id` | Session creation event |
| `wpaic_after_provider_request` | 3.4 | `$result, $params, $bot, $session` | Post-request processing (cost tracking) |
| `wpaic_before_log_message` | 4.5 | `$message, $response, $session, $bot` | Pre-logging event |

---

## 8. Error Response Format

All pipeline errors return a consistent JSON structure:

### 8.1 Validation Errors (Pre-Processing)

```json
{
    "success": false,
    "data": {
        "message": "User-friendly error message.",
        "code": "wpaic_validation_error"
    }
}
```

HTTP status: 400 (validation), 403 (auth/nonce), 404 (bot not found), 429 (rate limit).

### 8.2 Provider Errors

```json
{
    "success": false,
    "data": {
        "message": "Unable to reach the AI service. Please try again in a moment.",
        "code": "wpaic_provider_error"
    }
}
```

HTTP status: Maps from provider error code (session 03 error taxonomy).

### 8.3 Soft Rejections

Moderation and duplicate detection return success responses with flags:

```json
{
    "success": true,
    "data": {
        "moderated": true,
        "message": "I'm unable to process that request."
    }
}
```

```json
{
    "success": true,
    "data": {
        "duplicate": true
    }
}
```

---

## 9. Anti-Spam and Rate Limiting Addendum

This section extends the pipeline steps 1.6-1.11 with detailed contracts for each anti-spam mechanism.

### Reference Anchors

| Source Plugin | File | Lines | Relevance |
|---------------|------|-------|-----------|
| AxiaChat | `axiachat-ai/includes/class-aichat-ajax.php` | 131-200 | Honeypot, captcha, rate limit, spam signature, moderation pipeline |
| MxChat | `mxchat-basic/includes/class-mxchat-public.php` | N/A | No server-side spam checks (client-side only) |
| Baachal | `baachal/baachal.php` | N/A | No spam pipeline |

### 9.1 Honeypot Field Contract

**HTML contract** (rendered by shortcode/widget):
```html
<div class="wpaic-hp-wrap" aria-hidden="true" style="position:absolute;left:-9999px;height:0;overflow:hidden">
  <label for="wpaic_hp_{instance_id}">Leave empty</label>
  <input type="text" id="wpaic_hp_{instance_id}" name="wpaic_hp"
         tabindex="-1" autocomplete="off" value="">
</div>
```

**Design notes**:
- `aria-hidden="true"` prevents screen readers from announcing it
- `tabindex="-1"` prevents keyboard focus
- Positioned off-screen rather than `display:none` (some bots detect `display:none`)
- `autocomplete="off"` prevents browser autofill
- Instance-scoped `id` prevents duplicate ID warnings on multi-widget pages

**Server-side check** (pipeline step 1.6):
```php
$honeypot = sanitize_text_field( $_POST['wpaic_hp'] ?? '' );
if ( '' !== $honeypot ) {
    // Silent success (no error detail -- don't educate bots)
    wp_send_json_success( array( 'message' => '' ) );
    exit;
}
```

**Key**: Returns `success: true` with empty message, not an error. This prevents bots from detecting they were caught.

Ref: AXI `shortcode.php` honeypot input, `class-aichat-ajax.php` lines 142-148.

### 9.2 Rate Limiter Integration

**Consuming `lib/rate-limiter.php`** (existing infrastructure):

```php
$rate_result = wpaic_check_rate_limit( 'chat', wpaic_get_client_ip() );
```

**Configuration options** (from session 02 options schema):

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `wpaic_security_rate_limit` | int | 60 | Max requests per window |
| `wpaic_security_rate_window` | int | 60 | Window duration in seconds |

**Transient keys**: `wpaic_rate_chat_{ip_hash}` (per-window counter), `wpaic_rate_block_{ip_hash}` (temporary block flag, 300s TTL).

**Response on block**:
```json
{
  "success": false,
  "data": {
    "message": "Too many requests. Please wait a moment before trying again.",
    "code": "rate_limited",
    "retry_after": 60
  }
}
```

HTTP 429 with `Retry-After` header set to remaining window seconds.

**Extension hook**:
```php
$rate_result = apply_filters( 'wpaic_rate_limit_check', $rate_result, $session, $bot );
```

Allows custom rate limiting logic (e.g., per-user tiers, VIP bypass, geographic limits).

Ref: `lib/rate-limiter.php`, session 02 transient patterns.

### 9.3 Duplicate Message Detection

**Purpose**: Prevent double-submit from rapid clicking or network retries.

**Mechanism**: Transient-based hash with short TTL.

```php
$message_hash = md5( $session['session_token'] . '|' . $message );
$transient_key = 'wpaic_dup_' . substr( $message_hash, 0, 32 );

if ( false !== get_transient( $transient_key ) ) {
    wp_send_json_success( array( 'duplicate' => true ) );
    exit;
}

set_transient( $transient_key, 1, 10 ); // 10-second dedup window
```

**Key design decisions**:
- Hash includes session_token to scope dedup per-session (same message from different sessions is allowed)
- 10-second window is enough to catch double-clicks without blocking legitimate repeated questions
- Returns `success: true` with `duplicate: true` flag (soft -- not an error)
- Frontend handles duplicate flag by showing "Message already sent" text without error styling

Ref: AXI `aichat_spam_signature_check()` with `aichat_dup` code (lines 166-184).

### 9.4 Message Length Validation

**Configurable limits**:

| Level | Option | Default | Maximum |
|-------|--------|---------|---------|
| Bot-level | `$bot['input_max_length']` | 512 | 4000 |
| Absolute cap | hardcoded | - | 4000 |

```php
$max_length = min(
    (int) ( $bot['input_max_length'] ?? 512 ),
    4000  // absolute cap
);

if ( mb_strlen( $message ) > $max_length ) {
    wp_send_json_error(
        array( 'message' => sprintf( 'Message exceeds maximum length of %d characters.', $max_length ) ),
        400
    );
}
```

**Frontend enforcement** (complementary, not a replacement for server-side):
- `maxlength` attribute on textarea (set from `wpaicConfig.inputMaxLength`)
- Character counter showing remaining chars
- Submit button disabled when over limit

Ref: AXI lines 159-164 (hard max 4000 chars).

### 9.5 Captcha Integration Hook

**Design**: No built-in CAPTCHA. Instead, a filter hook allows third-party CAPTCHA services (reCAPTCHA, hCaptcha, Turnstile) to integrate without modifying core code.

```php
// Called during moderation step (1.10)
$captcha_valid = apply_filters( 'wpaic_validate_captcha', true, array(
    'token'      => sanitize_text_field( $_POST['wpaic_captcha_token'] ?? '' ),
    'action'     => 'wpaic_chat',
    'ip_address' => wpaic_get_client_ip(),
    'session'    => $session,
) );

if ( true !== $captcha_valid ) {
    wp_send_json_error(
        array( 'message' => 'Security verification failed. Please try again.' ),
        403
    );
}
```

**Frontend contract**: If CAPTCHA is enabled, the widget renders a CAPTCHA challenge element. The CAPTCHA token is submitted as `wpaic_captcha_token` in the chat request.

**Hook implementer responsibilities**:
- Verify token against CAPTCHA provider API
- Return `true` to allow, any other value to block
- Handle provider API failures gracefully (fail-open or fail-closed per configuration)

Ref: AXI `aichat_validate_captcha` filter (lines 186-200).

### 9.6 Moderation Hook Chain

**Design**: Sequential filter chain with soft-fail behavior.

```php
// Step 1: Built-in banned word check
$moderation = null;
if ( (bool) get_option( 'wpaic_security_moderation_enabled', false ) ) {
    $banned_words = array_filter( explode( "\n", get_option( 'wpaic_security_banned_words', '' ) ) );
    foreach ( $banned_words as $word ) {
        if ( false !== mb_stripos( $message, trim( $word ) ) ) {
            $moderation = new WP_Error( 'wpaic_moderated', 'Message contains restricted content.' );
            break;
        }
    }
}

// Step 2: External moderation hook
$moderation = apply_filters( 'wpaic_moderate_message', $moderation, $message, $bot, $session );
```

**Soft-fail response** (preserves session state, does not penalize user):
```json
{
  "success": true,
  "data": {
    "moderated": true,
    "message": "I'm unable to process that request. Please rephrase your question."
  }
}
```

**Hook chain behavior**:
- If any filter returns `WP_Error`, the pipeline stops and returns the moderated response
- Multiple moderation filters can be registered at different priorities
- Priority 5: content policy checks (fast, local)
- Priority 10: default (banned words)
- Priority 15: external API moderation (slow, network call)

**Logging**: Moderation blocks are logged via action hook:
```php
do_action( 'wpaic_moderation_blocked', $message, $session, $moderation );
```

Ref: AXI `aichat_run_moderation_checks()`, `aichat_record_moderation_block()` (lines 186-200).

### 9.7 Integration Summary

| Mechanism | Pipeline Step | Detection | Response | Extensible |
|-----------|--------------|-----------|----------|------------|
| Honeypot | 1.6 | Hidden field filled | Silent success (empty) | No |
| Rate limit | 1.7 | Transient counter | 429 + retry_after | Yes (filter) |
| Message length | 1.8 | `mb_strlen()` | 400 error | Per-bot config |
| Duplicate | 1.9 | Transient hash | Success + `duplicate` flag | No |
| CAPTCHA | 1.10 | External token verify | 403 error | Yes (filter) |
| Banned words | 1.10 | `mb_stripos()` scan | Success + `moderated` flag | Config |
| External moderation | 1.10 | API call via hook | Success + `moderated` flag | Yes (filter) |
| Usage limits | 1.11 | Daily aggregates | 429 error | Config |

---

## 10. Testability Notes

### Unit Testable

- Each pre-processing step in isolation (nonce, sanitization, bot resolution, rate limiting, etc.)
- Message array construction from history + context
- Response sanitization and suggestion extraction
- Error response formatting
- Cost calculation delegation

### Integration Testable

- Full pipeline flow: input -> pre-processing -> context -> dispatch -> post-processing -> response
- Session creation and ownership validation
- Rate limiting with transient state
- Tool continuation two-phase flow
- Streaming vs sync dispatch decision

### Mock Points

- Provider interface (mock `chat()` / `stream_chat()` for pipeline tests)
- Database (`$wpdb` mock for session/message operations)
- WordPress functions (`wp_verify_nonce`, `get_option`, `get_transient`)

---
