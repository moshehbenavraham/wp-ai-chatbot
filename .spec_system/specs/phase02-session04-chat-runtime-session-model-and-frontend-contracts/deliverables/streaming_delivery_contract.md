# Streaming Delivery Contract

**Session**: `phase02-session04-chat-runtime-session-model-and-frontend-contracts`
**Version**: 2.0
**Created**: 2026-03-01
**Resolves**: CFI-RUN-003 (runtime-side streaming consumption -- provider side resolved in session 03)
**Task**: T012

---

## 1. Overview

This document specifies the runtime-to-browser SSE delivery pipeline that consumes the provider-side streaming contract from session 03 (`streaming_transport_layer.md`). It covers two halves of the streaming path:

1. **Backend SSE Output** (PHP): HTTP headers, output buffer management, chunk-by-chunk write/flush, session timeout enforcement, and WordPress/server compatibility.
2. **Frontend SSE Consumption** (JavaScript): `EventSource` API usage, chunk event handling, progressive DOM rendering, temporary message pattern, connection lifecycle, fallback to sync AJAX, state accumulation, error handling, and tool-pending behavior.

The provider adapter emits normalized chunks via `$callback` (session 03 contract). This document defines how the runtime converts those callbacks into SSE wire events and how the frontend consumes and renders them.

### Design Boundary

```
Session 03 (Provider Side)          |  Session 04 (This Document)
------------------------------------|-----------------------------------
Provider API -> cURL WRITEFUNCTION  |  Runtime SSE Handler (PHP)
  -> parse native chunks            |    -> set HTTP headers
  -> normalize to chunk schema      |    -> flush output buffers
  -> emit via $callback             |    -> echo "data: {json}\n\n"
                                    |    -> flush()
                                    |
                                    |  Browser (Frontend JS)
                                    |    -> EventSource receives events
                                    |    -> parse JSON, dispatch by type
                                    |    -> progressive DOM rendering
                                    |    -> state accumulation
                                    |    -> connection lifecycle
```

### Reference Anchors

| Source Plugin | File | Lines | Relevance |
|---------------|------|-------|-----------|
| MxChat | `mxchat-basic/js/chat-script.js` | 795-1030 | Primary: Fetch-based SSE consumption, content accumulation, temporary message updates, `[DONE]` handling, JSON fallback detection |
| MxChat | `mxchat-basic/js/chat-script.js` | 408-415 | Streaming detection: `shouldUseStreaming(currentModel)` checks toggle AND model support |
| MxChat | `mxchat-basic/js/chat-script.js` | 475-485 | `shouldUseStreaming()`: `enable_streaming_toggle === 'on'` AND `isStreamingSupported(model)` |
| Session 03 | `streaming_transport_layer.md` | All | Provider-side chunk format, SSE wire protocol, connection lifecycle, cURL WRITEFUNCTION |
| AxiaChat | `axiachat-ai/assets/js/aichat-frontend.js` | N/A | No streaming -- sync AJAX only |
| Baachal | `baachal/assets/chatbot.js` | N/A | No streaming -- sync AJAX only |

---

## 2. Backend SSE Output (PHP)

The runtime is responsible for all HTTP-level concerns before, during, and after provider streaming. Provider adapters MUST NOT set headers, flush buffers, or write to output directly -- they only emit chunks via `$callback`.

### 2.1 HTTP Header Setup

Headers MUST be sent before invoking `$provider->stream_chat()`:

```php
// SSE content type -- tells browser to expect an event stream
header( 'Content-Type: text/event-stream; charset=UTF-8' );

// Prevent any caching of the stream
header( 'Cache-Control: no-cache, no-store, must-revalidate' );

// Keep the connection alive for the duration of the stream
header( 'Connection: keep-alive' );

// Bypass Nginx proxy buffering (critical for reverse-proxy setups)
header( 'X-Accel-Buffering: no' );
```

**Rationale**: These four headers are the minimum required for reliable SSE delivery across common hosting environments. `X-Accel-Buffering: no` is an Nginx-specific directive that prevents the reverse proxy from buffering the entire response before forwarding it to the client.

### 2.2 Output Buffer Cleanup

WordPress and PHP often have multiple output buffering layers active. All must be flushed and closed before streaming begins:

```php
// Flush and close ALL output buffer levels
// WordPress adds ob_start() in wp-settings.php; plugins may add more.
// ob_end_clean() discards buffered content (not ob_end_flush(), which would
// send stale buffered data before the stream starts).
while ( ob_get_level() ) {
    ob_end_clean();
}

// Disable gzip compression at the Apache level
if ( function_exists( 'apache_setenv' ) ) {
    apache_setenv( 'no-gzip', '1' );
}

// Disable PHP-level zlib output compression
ini_set( 'zlib.output_compression', '0' );
```

**Why `ob_end_clean()` not `ob_end_flush()`**: At this point, the SSE headers have been sent but no SSE data has been emitted. Any content in existing buffers (e.g., WordPress debug output, plugin notices) would corrupt the SSE stream if flushed. Discarding via `ob_end_clean()` ensures only valid SSE data reaches the client.

### 2.3 Chunk-by-Chunk Write and Flush

The runtime provides a `$callback` to the provider adapter. Each invocation writes one SSE event and flushes immediately:

```php
$callback = function ( $chunk ) {
    // Write SSE event: "data: {json}\n\n"
    echo 'data: ' . wp_json_encode( $chunk ) . "\n\n";

    // Flush any remaining output buffer (defensive -- should be 0 levels)
    if ( ob_get_level() ) {
        ob_flush();
    }

    // Flush the PHP output buffer to the web server
    flush();
};
```

**Wire format produced**:
```
data: {"type":"content","content":"Hello"}\n\n
data: {"type":"content","content":" world"}\n\n
data: {"type":"usage","usage":{"prompt_tokens":10,"completion_tokens":5,"total_tokens":15}}\n\n
data: {"type":"done","finish_reason":"stop"}\n\n
```

**Rules**:
- Each event is a single `data:` line followed by `\n\n`
- No `event:` field (all events use the default `message` type; type is inside the JSON)
- No `id:` field (reconnection is disabled; see Section 4.5)
- No `retry:` field (reconnection is disabled)
- Terminator is `data: {"type":"done"}\n\n` (NOT `data: [DONE]`)

### 2.4 Session Timeout Management

PHP defaults to a 30-second execution time limit, which is insufficient for long streaming responses. The runtime must extend the timeout and handle user disconnection:

```php
// Allow the stream to run for up to 120 seconds
// (configurable via get_option('wpaic_runtime_stream_timeout', 120))
$stream_timeout = (int) get_option( 'wpaic_runtime_stream_timeout', 120 );
set_time_limit( $stream_timeout + 10 ); // +10s buffer for post-stream cleanup

// Continue execution even if the client disconnects mid-stream
// This allows the runtime to perform post-stream processing
// (logging, cost tracking) even when the browser navigates away.
ignore_user_abort( true );
```

**Timeout enforcement within the callback**:

```php
$start_time = time();

$callback = function ( $chunk ) use ( &$start_time, $stream_timeout ) {
    // Check if stream has exceeded maximum duration
    if ( ( time() - $start_time ) > $stream_timeout ) {
        // Emit timeout error chunk
        echo 'data: ' . wp_json_encode( array(
            'type'  => 'error',
            'error' => array(
                'code'         => 504,
                'type'         => 'timeout',
                'message'      => 'Stream timeout exceeded.',
                'retryable'    => false,
                'user_message' => 'The response took too long. Please try again.',
            ),
        ) ) . "\n\n";

        // Emit done chunk to signal stream termination
        echo "data: {\"type\":\"done\"}\n\n";
        flush();

        // Note: returning from the callback does not abort cURL.
        // The adapter's CURLOPT_LOW_SPEED_TIME (30s) will eventually
        // terminate the connection if the runtime stops consuming.
        return;
    }

    // Normal chunk forwarding
    echo 'data: ' . wp_json_encode( $chunk ) . "\n\n";
    if ( ob_get_level() ) {
        ob_flush();
    }
    flush();
};
```

### 2.5 Cloudflare Padding

Some CDN/proxy layers (notably Cloudflare) buffer responses smaller than 1KB before forwarding. To ensure the first SSE event reaches the browser promptly, emit a padding comment before the first data event:

```php
// SSE comment (colon prefix) -- ignored by EventSource but fills proxy buffers
echo ': ' . str_repeat( ' ', 1024 ) . "\n\n";
flush();
```

This MUST be emitted after headers are sent and buffers are cleaned, but before the provider callback begins.

### 2.6 WordPress AJAX Termination

The streaming endpoint uses `admin-ajax.php` (not REST API) because:
1. AJAX supports `nopriv` actions for anonymous users
2. AJAX allows direct output control without REST response wrapping
3. Consistent with the sync chat endpoint (same AJAX infrastructure)

**Critical**: The handler MUST call `exit` (or `die`) after streaming completes to prevent WordPress from appending `0` to the output, which is the standard WordPress AJAX termination behavior:

```php
// After stream_chat() returns and post-processing completes:
exit;
```

### 2.7 WordPress and Server Compatibility Notes

| Server/Proxy | Issue | Mitigation |
|-------------|-------|------------|
| **Nginx** (reverse proxy) | Buffers upstream responses by default (`proxy_buffering on`) | `X-Accel-Buffering: no` header disables per-response |
| **Nginx** (FastCGI) | `fastcgi_buffering on` buffers PHP-FPM output | `X-Accel-Buffering: no` also disables FastCGI buffering |
| **Apache mod_deflate** | Compresses SSE stream, delaying delivery until buffer fills | `apache_setenv('no-gzip', '1')` disables per-request |
| **Apache mod_proxy** | Buffers proxied responses | `SetEnv proxy-sendchunked 1` in `.htaccess` (server config, not runtime) |
| **Cloudflare** | Buffers responses < 1KB | 1KB padding comment (Section 2.5) |
| **Cloudflare** | "Auto Minify" / "Rocket Loader" may interfere | N/A for SSE (only affects HTML/JS/CSS assets) |
| **php-fpm** | Multiple output buffering layers | `ob_end_clean()` loop (Section 2.2) |
| **WordPress core** | `ob_start()` in `wp-settings.php` | Cleared by output buffer loop |
| **WordPress plugins** | May add their own `ob_start()` for caching/minification | Cleared by output buffer loop |
| **LiteSpeed** | Buffers by default; respects `X-Accel-Buffering: no` in recent versions | Same header; LiteSpeed 5.4+ required |
| **Shared hosting** | May impose `max_execution_time` < 120s via `php.ini` override | `set_time_limit()` may be disabled; graceful degradation to sync mode |

### 2.8 Complete Backend Pseudocode

```php
// 1. Pre-stream: headers and buffer cleanup
header( 'Content-Type: text/event-stream; charset=UTF-8' );
header( 'Cache-Control: no-cache, no-store, must-revalidate' );
header( 'Connection: keep-alive' );
header( 'X-Accel-Buffering: no' );

while ( ob_get_level() ) {
    ob_end_clean();
}
if ( function_exists( 'apache_setenv' ) ) {
    apache_setenv( 'no-gzip', '1' );
}
ini_set( 'zlib.output_compression', '0' );

$stream_timeout = (int) get_option( 'wpaic_runtime_stream_timeout', 120 );
set_time_limit( $stream_timeout + 10 );
ignore_user_abort( true );

// 2. Cloudflare padding
echo ': ' . str_repeat( ' ', 1024 ) . "\n\n";
flush();

// 3. State accumulators
$accumulated_content    = '';
$accumulated_usage      = array();
$accumulated_tool_calls = array();
$finish_reason          = null;
$start_time             = time();

// 4. Callback for provider adapter
$callback = function ( $chunk ) use (
    &$accumulated_content,
    &$accumulated_usage,
    &$accumulated_tool_calls,
    &$finish_reason,
    $start_time,
    $stream_timeout
) {
    // Timeout check
    if ( ( time() - $start_time ) > $stream_timeout ) {
        echo 'data: ' . wp_json_encode( array(
            'type'  => 'error',
            'error' => array(
                'code'         => 504,
                'type'         => 'timeout',
                'message'      => 'Stream timeout exceeded.',
                'retryable'    => false,
                'user_message' => 'The response took too long. Please try again.',
            ),
        ) ) . "\n\n";
        echo "data: {\"type\":\"done\"}\n\n";
        flush();
        return;
    }

    // Forward chunk to browser
    echo 'data: ' . wp_json_encode( $chunk ) . "\n\n";
    if ( ob_get_level() ) {
        ob_flush();
    }
    flush();

    // Accumulate for post-processing
    switch ( $chunk['type'] ) {
        case 'content':
            $accumulated_content .= $chunk['content'];
            break;
        case 'usage':
            $accumulated_usage = $chunk['usage'];
            break;
        case 'tool_call':
            wpaic_accumulate_tool_call( $accumulated_tool_calls, $chunk['tool_call'] );
            break;
        case 'done':
            $finish_reason = $chunk['finish_reason'] ?? 'stop';
            break;
    }
};

// 5. Invoke provider streaming
$result = $provider->stream_chat( $messages, $params, $callback );

// 6. Post-stream processing (same as sync mode, but response already delivered)
// - Response sanitization (for storage only)
// - Cost calculation
// - Conversation logging to wp_wpaic_messages
// - Session aggregate updates (tokens, cost, message count)
// - Action: wpaic_after_provider_request

// 7. Terminate
exit;
```

---

## 3. Frontend SSE Consumption (JavaScript)

### 3.1 Streaming Decision

The frontend decides whether to use streaming based on two conditions:

```javascript
/**
 * Determine if the current request should use streaming.
 *
 * @param {object} instance - Chat widget instance from the registry.
 * @returns {boolean} True if streaming should be attempted.
 */
function shouldStream(instance) {
    // Global streaming toggle (from wp_localize_script)
    var globalEnabled = wpaicChatData.streamEnabled === true
        || wpaicChatData.streamEnabled === '1';

    // Per-bot streaming support (set by provider/model capabilities)
    var botSupported = instance.bot.streamSupported === true;

    return globalEnabled && botSupported;
}
```

Ref: MXC `shouldUseStreaming()` lines 475-485 -- checks `enable_streaming_toggle === 'on'` AND `isStreamingSupported(model)`.

### 3.2 Why EventSource (Not Fetch Streaming)

The frontend uses the `EventSource` API for SSE consumption. Rationale:

| Concern | EventSource | fetch() + ReadableStream |
|---------|-------------|--------------------------|
| Automatic reconnection | Built-in (configurable via `retry:`) | Manual implementation required |
| SSE parsing | Built-in (handles `data:`, `event:`, `id:`, comments) | Manual line splitting and parsing |
| Browser support | All modern browsers + IE11 polyfill available | ReadableStream not in IE11; limited mobile support |
| API simplicity | 6 lines to consume a stream | 30+ lines for reader loop, decoder, buffer management |
| Connection lifecycle | `onopen`, `onmessage`, `onerror` events | Manual state tracking |
| Memory pressure | Browser manages buffering | Developer must manage TextDecoder and buffer |

**Trade-off**: `EventSource` only supports GET requests. The streaming endpoint URL must encode the request parameters (nonce, message, session token) as query parameters or use a two-phase approach: POST the message via standard AJAX first (which returns a stream URL with a one-time token), then open `EventSource` on that URL. This contract uses the **two-phase approach**:

1. **Phase 1**: POST message via standard AJAX to `wpaic_chat_init_stream` action. The server validates the request (nonce, session, rate limit), stores the pending message, and returns a one-time stream token.
2. **Phase 2**: Open `EventSource` on the stream URL with the one-time token. The server retrieves the pending message, dispatches to the provider, and streams the response.

If the two-phase overhead is unacceptable in a future optimization pass, the implementation MAY switch to `fetch()` + `ReadableStream` as a behind-the-scenes change without altering the rest of this contract (chunk format, DOM rendering, state accumulation remain identical).

**Note on MXC reference**: MxChat uses `fetch()` + `ReadableStream` (lines 846-1028) because it sends the message payload as POST body. Our two-phase design avoids this constraint while gaining EventSource benefits.

### 3.3 Two-Phase Stream Initiation

#### Phase 1: Initialize Stream (POST)

```javascript
/**
 * Initiate a streaming chat request.
 *
 * Phase 1: POST the message to get a one-time stream token.
 * Phase 2: Open EventSource on the stream URL.
 *
 * @param {object} instance - Chat widget instance.
 * @param {string} message - User message text.
 */
function sendStreamingMessage(instance, message) {
    var formData = new FormData();
    formData.append('action', 'wpaic_chat_init_stream');
    formData.append('nonce', wpaicChatData.nonce);
    formData.append('bot_slug', instance.bot.slug);
    formData.append('message', message);
    formData.append('session_token', instance.sessionToken);

    // Page context (if contextual awareness is enabled)
    var pageContext = WPAICChat.getPageContext();
    if (pageContext) {
        formData.append('page_context', JSON.stringify(pageContext));
    }

    // Honeypot field (anti-spam)
    var hpField = instance.element.querySelector('input[name="wpaic_hp"]');
    if (hpField) {
        formData.append('wpaic_hp', hpField.value);
    }

    setUiState(instance, 'connecting');

    fetch(wpaicChatData.ajaxUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
    })
    .then(function (response) {
        if (!response.ok) {
            throw new Error('Stream init failed: HTTP ' + response.status);
        }
        return response.json();
    })
    .then(function (data) {
        if (!data.success || !data.data || !data.data.stream_token) {
            // Server rejected the request (validation, rate limit, etc.)
            handleSyncResponse(instance, data);
            return;
        }

        // Phase 2: open EventSource
        openEventSourceStream(instance, data.data.stream_token);
    })
    .catch(function (err) {
        // Network error on init -- fall back to sync AJAX
        fallbackToSync(instance, message);
    });
}
```

#### Phase 2: Open EventSource (GET)

```javascript
/**
 * Open an EventSource connection to consume the SSE stream.
 *
 * @param {object} instance - Chat widget instance.
 * @param {string} streamToken - One-time token from Phase 1.
 */
function openEventSourceStream(instance, streamToken) {
    // Build the stream URL with the one-time token
    var streamUrl = wpaicChatData.ajaxUrl
        + '?action=wpaic_chat_stream'
        + '&stream_token=' + encodeURIComponent(streamToken);

    var source = new EventSource(streamUrl, {
        withCredentials: true,  // Send cookies for same-origin auth
    });

    // Track the EventSource on the instance for cleanup
    instance.activeStream = source;

    // State accumulators (see Section 6)
    var state = {
        content: '',
        usage: null,
        toolCalls: [],
        finishReason: null,
        chunkCount: 0,
        lastChunkTime: Date.now(),
    };

    // No-progress timeout timer (see Section 4.2)
    var progressTimer = setInterval(function () {
        if (Date.now() - state.lastChunkTime > 45000) {
            clearInterval(progressTimer);
            source.close();
            handleStreamError(instance, {
                code: 504,
                user_message: 'The response appears to have stalled. Please try again.',
            }, state.content);
        }
    }, 5000);

    source.onopen = function () {
        setUiState(instance, 'streaming');
    };

    source.onmessage = function (event) {
        state.lastChunkTime = Date.now();

        var chunk;
        try {
            chunk = JSON.parse(event.data);
        } catch (e) {
            return; // Skip malformed chunks
        }

        handleChunk(instance, chunk, state, source, progressTimer);
    };

    source.onerror = function () {
        clearInterval(progressTimer);
        source.close();

        if (state.chunkCount === 0) {
            // No data received -- fall back to sync
            fallbackToSync(instance, instance.pendingMessage);
        } else {
            // Partial data received -- show error with partial content
            handleStreamError(instance, {
                code: 0,
                user_message: wpaicChatData.i18n.errorConnection
                    || 'Connection lost. Partial response shown.',
            }, state.content);
        }
    };
}
```

### 3.4 Chunk Event Handling

Each SSE `message` event contains a JSON-encoded chunk matching the session 03 normalized chunk schema. The handler dispatches by `type`:

```javascript
/**
 * Process a single chunk from the SSE stream.
 *
 * @param {object} instance - Chat widget instance.
 * @param {object} chunk - Parsed chunk object ({type, ...}).
 * @param {object} state - Accumulator state.
 * @param {EventSource} source - The active EventSource.
 * @param {number} progressTimer - setInterval ID for no-progress detection.
 */
function handleChunk(instance, chunk, state, source, progressTimer) {
    switch (chunk.type) {
        case 'content':
            state.content += chunk.content;
            state.chunkCount += 1;
            updateStreamingMessage(instance, state.content);

            instance.element.dispatchEvent(new CustomEvent('wpaic:stream-chunk', {
                detail: { content: chunk.content, accumulated: state.content },
                bubbles: true,
            }));
            break;

        case 'tool_call':
            accumulateToolCall(state.toolCalls, chunk.tool_call);

            instance.element.dispatchEvent(new CustomEvent('wpaic:tool-call', {
                detail: { toolCall: chunk.tool_call },
                bubbles: true,
            }));
            break;

        case 'usage':
            state.usage = chunk.usage;
            break;

        case 'error':
            clearInterval(progressTimer);
            source.close();
            instance.activeStream = null;
            handleStreamError(instance, chunk.error, state.content);
            break;

        case 'done':
            clearInterval(progressTimer);
            source.close();
            instance.activeStream = null;
            state.finishReason = chunk.finish_reason || 'stop';
            finalizeStream(instance, state);
            break;
    }
}
```

### 3.5 Progressive DOM Rendering

Streaming content is rendered progressively as chunks arrive. The rendering strategy uses a **temporary message element** that is updated with each content chunk, following the MXC `bot-message.temporary-message` pattern.

**Key safety rules**:
- During streaming, use `textContent` assignment followed by sanitized HTML rendering -- never raw `innerHTML` with unsanitized provider output.
- Use `DocumentFragment` for batched DOM updates to minimize reflows.
- Append text nodes directly when markdown rendering is disabled.

```javascript
/**
 * Update the streaming message element with accumulated content.
 *
 * Creates the temporary message element on the first chunk.
 * Updates content on subsequent chunks.
 *
 * @param {object} instance - Chat widget instance.
 * @param {string} accumulatedContent - Full accumulated content so far.
 */
function updateStreamingMessage(instance, accumulatedContent) {
    var tempMessage = instance.messagesEl.querySelector(
        '.wpaic-chatbot__message--streaming'
    );

    if (!tempMessage) {
        // First chunk: create the temporary message element
        tempMessage = document.createElement('div');
        tempMessage.className = 'wpaic-chatbot__message'
            + ' wpaic-chatbot__message--bot'
            + ' wpaic-chatbot__message--streaming';

        // Append via DocumentFragment for clean DOM insertion
        var fragment = document.createDocumentFragment();
        fragment.appendChild(tempMessage);
        instance.messagesEl.appendChild(fragment);

        // Remove thinking/typing indicator
        var thinkingEl = instance.messagesEl.querySelector(
            '.wpaic-chatbot__thinking'
        );
        if (thinkingEl) {
            thinkingEl.parentNode.removeChild(thinkingEl);
        }

        // Fire stream-start event
        instance.element.dispatchEvent(new CustomEvent('wpaic:stream-start', {
            bubbles: true,
        }));
    }

    // Render accumulated content (sanitized)
    // Option A: Plain text mode (no markdown)
    if (!instance.bot.markdownEnabled) {
        tempMessage.textContent = accumulatedContent;
    }
    // Option B: Markdown/HTML mode (sanitized)
    else {
        var sanitized = WPAICChat.sanitizeHtml(accumulatedContent);
        tempMessage.innerHTML = '';

        var renderFragment = document.createDocumentFragment();
        var wrapper = document.createElement('div');
        wrapper.innerHTML = sanitized;

        while (wrapper.firstChild) {
            renderFragment.appendChild(wrapper.firstChild);
        }
        tempMessage.appendChild(renderFragment);
    }

    // Auto-scroll to keep latest content visible
    scrollToBottom(instance);
}
```

**Why DocumentFragment**: Direct `innerHTML` replacement on each chunk causes the browser to destroy and recreate all child nodes, which is expensive and causes visual flicker. Using `DocumentFragment`:
1. Creates the new DOM subtree off-screen
2. Appends it in a single reflow operation
3. Avoids intermediate layout thrashing during rapid chunk updates

**Why NOT incremental text node appending**: While appending individual text nodes for each chunk delta would be the most efficient approach for plain text, it fails when markdown rendering is enabled because markdown structures (e.g., `**bold**`) may span multiple chunks. The accumulated-content-with-full-rerender approach ensures correct rendering at the cost of slightly more DOM work per chunk.

### 3.6 Temporary Message Pattern (MXC Pattern)

The streaming message lifecycle follows the MXC `bot-message.temporary-message` pattern:

```
[thinking indicator visible]
    |
    | first content chunk arrives
    v
[thinking indicator removed]
[temporary message created: .wpaic-chatbot__message--streaming]
    |
    | subsequent content chunks
    v
[temporary message content updated progressively]
    |
    | done chunk received
    v
[--streaming class removed]
[final sanitization applied]
[suggestions extracted and rendered]
[UI state -> idle]
```

**CSS contract**: The `.wpaic-chatbot__message--streaming` class enables:
- Blinking cursor indicator (via CSS `::after` pseudo-element)
- Reduced opacity or other visual cue that content is still arriving
- Disabled user interaction (e.g., no copy button until finalized)

Ref: MXC lines 408-415 -- on stream completion, `.bot-message.temporary-message` class is removed, signaling the message is final.

---

## 4. Connection Lifecycle

### 4.1 State Diagram

```
[idle]
  |
  | sendStreamingMessage() called
  v
[connecting]  ----fetch error----> [error] ----> [idle]
  |
  | Phase 1 POST succeeds, EventSource opens
  v
[streaming]
  |               |               |
  | done chunk    | error chunk   | onerror / timeout
  v               v               v
[finalizing]    [error]          [error]
  |               |               |
  v               v               v
[idle]          [idle]           [idle]
```

### 4.2 Timeout Handling

| Timeout | Value | Enforced By | Behavior on Expiry |
|---------|-------|-------------|-------------------|
| Phase 1 init | Browser default (~60s) | Browser fetch | `catch` handler triggers sync fallback |
| Stream duration | 120 seconds | Runtime PHP (`set_time_limit`) | Emit error chunk + done chunk |
| Chunk stall (backend) | 30 seconds | Provider adapter (`CURLOPT_LOW_SPEED_TIME`) | Adapter emits error + done chunks |
| No-progress (frontend) | 45 seconds | Frontend JS (`setInterval`) | Close EventSource, show error with partial content |

**No-progress timeout** (frontend):

The frontend runs a 5-second polling interval that checks whether any chunk has been received in the last 45 seconds. If not, the stream is considered stalled:

```javascript
var progressTimer = setInterval(function () {
    if (Date.now() - state.lastChunkTime > 45000) {
        clearInterval(progressTimer);
        source.close();
        handleStreamError(instance, {
            code: 504,
            user_message: 'The response appears to have stalled. Please try again.',
        }, state.content);
    }
}, 5000);
```

The 45-second frontend timeout is intentionally longer than the 30-second backend chunk stall timeout. Under normal conditions, the backend will detect the stall first and emit an error chunk. The frontend timeout is a safety net for cases where the backend fails silently (e.g., PHP process killed by OOM).

### 4.3 Done Signal

The `{type:'done'}` chunk is the authoritative stream termination signal:

```javascript
case 'done':
    clearInterval(progressTimer);
    source.close();          // Close the EventSource connection
    instance.activeStream = null;
    state.finishReason = chunk.finish_reason || 'stop';
    finalizeStream(instance, state);
    break;
```

After receiving `done`, the frontend:
1. Clears the no-progress timer
2. Closes the `EventSource` connection
3. Removes the `--streaming` CSS class from the temporary message
4. Applies final sanitization and markdown rendering
5. Extracts and renders suggestions (if present)
6. Transitions UI state to `idle`
7. Fires `wpaic:stream-end` and `wpaic:response-received` events

### 4.4 Error During Active Stream

When an error occurs after content has been emitted:

1. **Partial content is preserved** -- it is NOT discarded
2. Error message is appended below the partial content
3. UI transitions to `error` state
4. The user can send a new message (which starts a fresh stream)

```javascript
/**
 * Handle a stream error, preserving any partial content.
 *
 * @param {object} instance - Chat widget instance.
 * @param {object} error - Error object with code, user_message.
 * @param {string} partialContent - Content accumulated before the error.
 */
function handleStreamError(instance, error, partialContent) {
    var tempMessage = instance.messagesEl.querySelector(
        '.wpaic-chatbot__message--streaming'
    );

    if (tempMessage) {
        // Mark as partial (not streaming, not complete)
        tempMessage.classList.remove('wpaic-chatbot__message--streaming');
        tempMessage.classList.add('wpaic-chatbot__message--partial');
    }

    // Append error notice below the partial content
    var errorEl = document.createElement('div');
    errorEl.className = 'wpaic-chatbot__error';
    errorEl.textContent = error.user_message
        || wpaicChatData.i18n.errorGeneric
        || 'An error occurred.';
    instance.messagesEl.appendChild(errorEl);

    setUiState(instance, 'error');
    scrollToBottom(instance);

    // Fire error event
    instance.element.dispatchEvent(new CustomEvent('wpaic:error', {
        detail: {
            error: error,
            partialContent: partialContent,
            streamed: true,
        },
        bubbles: true,
    }));
}
```

**CSS contract**: `.wpaic-chatbot__message--partial` indicates a message that was cut short. Implementations may style it with a visual indicator (e.g., dashed bottom border, warning icon).

### 4.5 Reconnection Policy

**Reconnection is disabled**. The SSE stream does not include `id:` fields or `retry:` directives. `EventSource` automatic reconnection is suppressed by closing the connection in the `onerror` handler.

Rationale (consistent with session 03 Section 7):
- Mid-stream errors are NOT retried because reconnecting would produce duplicate or inconsistent output
- The provider adapter has already consumed the request tokens
- Partial content is preserved for the user to read

If the stream fails before any content is received (zero chunks), the frontend falls back to sync AJAX (Section 5).

---

## 5. Fallback Strategy

### 5.1 When Streaming is Unavailable

Streaming may be unavailable because:
- `wpaic_runtime_stream_enabled` option is `false` (admin toggle)
- The provider does not implement `Streamable_Provider_Interface`
- The model does not support streaming (per model registry capabilities)
- Server configuration prevents SSE delivery (detected at runtime)

When `shouldStream()` returns `false`, the frontend sends a standard AJAX request to the `wpaic_chat` action and processes the JSON response synchronously.

### 5.2 Automatic Fallback on Stream Failure

If the streaming path fails (Phase 1 network error, EventSource `onerror` with zero chunks received), the frontend falls back to sync AJAX for the same message:

```javascript
/**
 * Fall back to synchronous AJAX when streaming fails.
 * Only called when zero content chunks were received.
 *
 * @param {object} instance - Chat widget instance.
 * @param {string} message - The original user message.
 */
function fallbackToSync(instance, message) {
    // Clean up any streaming UI artifacts
    var tempMessage = instance.messagesEl.querySelector(
        '.wpaic-chatbot__message--streaming'
    );
    if (tempMessage) {
        tempMessage.parentNode.removeChild(tempMessage);
    }

    // Restore thinking indicator
    appendThinkingMessage(instance);

    // Send via standard AJAX
    sendSyncMessage(instance, message);
}
```

**Retry policy**: One fallback attempt (sync AJAX). If the sync request also fails, the error is shown to the user. No further automatic retries.

### 5.3 Server-Side JSON Fallback Detection

If the streaming endpoint detects that SSE cannot be delivered (e.g., `headers_sent()` returns `true` before the runtime sets SSE headers), it falls back to a standard JSON response:

```php
if ( headers_sent() ) {
    // Cannot do SSE -- fall back to sync JSON response
    $result = $provider->chat( $messages, $params );
    wp_send_json_success( array(
        'message'     => $result['message'],
        'suggestions' => wpaic_extract_suggestions( $result['message'] ),
    ) );
    exit;
}
```

The frontend detects JSON responses by checking the `Content-Type` header (see Section 3.3 Phase 1 response handling).

---

## 6. State Accumulation

During streaming, the frontend accumulates state from individual chunks to reconstruct the complete response. This accumulated state is used for:
- Progressive DOM rendering (content)
- Final sanitization and suggestion extraction (content)
- Usage tracking (usage)
- Tool continuation (tool_calls)
- Analytics events (finish_reason)

### 6.1 Accumulator Schema

```javascript
var state = {
    content: '',        // string: concatenated content chunk deltas
    usage: null,        // object|null: {prompt_tokens, completion_tokens, total_tokens}
    toolCalls: [],      // array: accumulated tool call objects
    finishReason: null, // string|null: 'stop'|'length'|'tool_calls'|'content_filter'
    chunkCount: 0,      // int: number of content chunks received
    lastChunkTime: 0,   // int: Date.now() of last chunk (for timeout detection)
};
```

### 6.2 Content Accumulation

Each `{type:'content', content:'...'}` chunk's `content` field is appended to `state.content`:

```javascript
state.content += chunk.content;
state.chunkCount += 1;
```

The accumulated `state.content` is passed to `updateStreamingMessage()` for rendering. On stream finalization, the complete `state.content` is used for final sanitization, suggestion extraction, and the `wpaic:response-received` event.

### 6.3 Usage Merging

Usage data arrives as a single `{type:'usage'}` chunk, typically just before `done`. The frontend stores it directly:

```javascript
state.usage = chunk.usage;
// chunk.usage = { prompt_tokens: N, completion_tokens: N, total_tokens: N }
```

If multiple usage chunks are received (not expected, but defensive), the last one wins.

Usage data is included in the `wpaic:stream-end` event detail for analytics or UI display.

### 6.4 Finish Reason Detection

The `finish_reason` field on the `done` chunk indicates why the model stopped generating:

| Value | Meaning | Frontend Behavior |
|-------|---------|-------------------|
| `stop` | Model completed normally | Finalize as complete response |
| `length` | Output token limit reached | Finalize; optionally show "response truncated" notice |
| `tool_calls` | Model wants to invoke tools | Trigger tool continuation flow (Section 8) |
| `content_filter` | Content was filtered by provider safety | Finalize; show filtered-content notice |

```javascript
state.finishReason = chunk.finish_reason || 'stop';
```

---

## 7. Error Chunk Handling

Error chunks follow the session 03 error schema and may arrive at any point during the stream. The frontend handles them as follows:

### 7.1 Error Chunk Schema

```json
{
    "type": "error",
    "error": {
        "code": 500,
        "type": "model_error",
        "message": "Internal server error from provider.",
        "retryable": false,
        "user_message": "An error occurred while generating the response. Please try again."
    }
}
```

### 7.2 Error Display Strategy

| Scenario | Content Received? | Behavior |
|----------|-------------------|----------|
| Error before any content | No (`state.chunkCount === 0`) | Remove streaming message, show error in chat bubble |
| Error after partial content | Yes (`state.chunkCount > 0`) | Keep partial content, append error notice below it |

```javascript
case 'error':
    clearInterval(progressTimer);
    source.close();
    instance.activeStream = null;

    if (state.chunkCount === 0) {
        // No content yet -- remove temporary message and show clean error
        var tempMsg = instance.messagesEl.querySelector(
            '.wpaic-chatbot__message--streaming'
        );
        if (tempMsg) {
            tempMsg.parentNode.removeChild(tempMsg);
        }
    }

    handleStreamError(instance, chunk.error, state.content);
    break;
```

### 7.3 Error After Done

If the runtime emits an error chunk AFTER a done chunk (should not happen per session 03 contract, but defensively handled):

```javascript
// In handleChunk, done sets instance.activeStream = null.
// Subsequent chunks on a closed EventSource are ignored by the browser.
// No defensive code needed -- EventSource.close() stops event dispatch.
```

### 7.4 Network-Level Errors

Network errors (DNS failure, TCP reset, TLS error) do not produce error chunks. They trigger the `EventSource.onerror` handler:

```javascript
source.onerror = function () {
    clearInterval(progressTimer);
    source.close();

    if (state.chunkCount === 0) {
        fallbackToSync(instance, instance.pendingMessage);
    } else {
        handleStreamError(instance, {
            code: 0,
            user_message: wpaicChatData.i18n.errorConnection
                || 'Connection lost. Partial response shown.',
        }, state.content);
    }
};
```

---

## 8. Tool Pending in Streaming

When the AI model decides to invoke a tool during a streaming response, `{type:'tool_call'}` chunks arrive interleaved with (or instead of) content chunks. The `done` chunk carries `finish_reason: 'tool_calls'`.

### 8.1 Tool Call Chunk Structure

Tool call chunks stream incrementally (session 03 schema):

```json
{"type":"tool_call","tool_call":{"index":0,"id":"call_abc123","function":{"name":"get_weather","arguments":""}}}
{"type":"tool_call","tool_call":{"index":0,"id":"","function":{"name":"","arguments":"{\"loc"}}}
{"type":"tool_call","tool_call":{"index":0,"id":"","function":{"name":"","arguments":"ation\""}}}
{"type":"tool_call","tool_call":{"index":0,"id":"","function":{"name":"","arguments":":\"NYC\"}"}}}
```

The first chunk for each tool call (identified by `index`) carries the `id` and `function.name`. Subsequent chunks for the same index carry only argument deltas.

### 8.2 Frontend Tool Call Accumulation

```javascript
/**
 * Accumulate tool call deltas into complete tool call objects.
 *
 * @param {Array} toolCalls - Accumulator array (mutated in place).
 * @param {object} delta - Tool call delta from chunk.
 */
function accumulateToolCall(toolCalls, delta) {
    var index = delta.index;

    if (!toolCalls[index]) {
        // First chunk for this tool call
        toolCalls[index] = {
            id: delta.id,
            name: delta.function.name,
            arguments: delta.function.arguments || '',
        };
    } else {
        // Subsequent chunk -- append arguments delta
        toolCalls[index].arguments += delta.function.arguments || '';
    }
}
```

### 8.3 Tool Continuation Flow

When `finish_reason === 'tool_calls'`:

```javascript
function finalizeStream(instance, state) {
    // ... common finalization (remove --streaming class, etc.)

    if (state.finishReason === 'tool_calls' && state.toolCalls.length > 0) {
        handleToolPending(instance, state);
        return; // Do NOT transition to idle yet
    }

    // Normal completion path
    setUiState(instance, 'idle');
    // ...
}
```

**Tool pending behavior**:

1. The streaming message (if any content was emitted alongside tool calls) is finalized as a partial response.
2. A "tool execution" indicator is shown (e.g., spinner with "Running tools...").
3. The frontend sends a tool continuation request (standard AJAX POST, not streaming) with the accumulated tool call results.
4. The continuation response may itself be streamed (another EventSource cycle) or synchronous.

```javascript
/**
 * Handle a tool-pending finish_reason by sending tool results
 * back to the server for continuation.
 *
 * @param {object} instance - Chat widget instance.
 * @param {object} state - Accumulated stream state.
 */
function handleToolPending(instance, state) {
    setUiState(instance, 'tool-pending');

    // Parse complete tool call arguments
    var completedCalls = state.toolCalls.map(function (tc) {
        var args;
        try {
            args = JSON.parse(tc.arguments);
        } catch (e) {
            args = {};
        }
        return {
            call_id: tc.id,
            name: tc.name,
            arguments: args,
        };
    });

    // Fire event for custom tool execution (extensibility)
    var toolEvent = new CustomEvent('wpaic:tool-pending', {
        detail: { toolCalls: completedCalls },
        bubbles: true,
        cancelable: true,
    });
    instance.element.dispatchEvent(toolEvent);

    if (toolEvent.defaultPrevented) {
        // Custom handler will manage tool execution and continuation
        return;
    }

    // Default: send tool calls to server for server-side execution
    var formData = new FormData();
    formData.append('action', 'wpaic_chat');
    formData.append('nonce', wpaicChatData.nonce);
    formData.append('continue_tool', '1');
    formData.append('session_token', instance.sessionToken);
    formData.append('bot_slug', instance.bot.slug);
    formData.append('tool_calls', JSON.stringify(completedCalls));

    fetch(wpaicChatData.ajaxUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
        if (data.success) {
            handleSyncResponse(instance, data);
        } else {
            handleStreamError(instance, {
                code: 500,
                user_message: 'Tool execution failed. Please try again.',
            }, state.content);
        }
    })
    .catch(function () {
        handleStreamError(instance, {
            code: 0,
            user_message: 'Connection lost during tool execution.',
        }, state.content);
    });
}
```

### 8.4 Interleaved Content and Tool Calls

A model may emit content chunks AND tool call chunks in the same stream. For example, the model might say "Let me check the weather for you" (content) and then emit tool call chunks. The frontend handles both simultaneously:

- Content chunks update the streaming message DOM
- Tool call chunks accumulate in `state.toolCalls`
- On `done` with `finish_reason: 'tool_calls'`, the content is finalized and tool continuation begins

---

## 9. Stream Finalization

```javascript
/**
 * Finalize a completed stream: apply final rendering, extract
 * suggestions, update UI state, and fire completion events.
 *
 * @param {object} instance - Chat widget instance.
 * @param {object} state - Accumulated stream state.
 */
function finalizeStream(instance, state) {
    var tempMessage = instance.messagesEl.querySelector(
        '.wpaic-chatbot__message--streaming'
    );

    if (tempMessage) {
        // Remove streaming indicator
        tempMessage.classList.remove('wpaic-chatbot__message--streaming');

        // Apply final sanitization and rendering (re-render from accumulated content)
        var sanitized = WPAICChat.sanitizeHtml(state.content);
        tempMessage.innerHTML = sanitized;
    }

    // Handle tool_calls finish reason separately
    if (state.finishReason === 'tool_calls' && state.toolCalls.length > 0) {
        handleToolPending(instance, state);
        return;
    }

    // Handle length-truncated response
    if (state.finishReason === 'length') {
        var truncNotice = document.createElement('div');
        truncNotice.className = 'wpaic-chatbot__notice wpaic-chatbot__notice--truncated';
        truncNotice.textContent = wpaicChatData.i18n.responseTruncated
            || 'Response was truncated due to length limits.';
        instance.messagesEl.appendChild(truncNotice);
    }

    // Handle content filter
    if (state.finishReason === 'content_filter') {
        var filterNotice = document.createElement('div');
        filterNotice.className = 'wpaic-chatbot__notice wpaic-chatbot__notice--filtered';
        filterNotice.textContent = wpaicChatData.i18n.contentFiltered
            || 'Some content was filtered by the AI provider.';
        instance.messagesEl.appendChild(filterNotice);
    }

    // Extract and render suggestions
    var suggestions = WPAICChat.extractSuggestions(state.content);
    if (suggestions.length > 0) {
        renderSuggestions(instance, suggestions);
    }

    // Store usage data on instance for analytics
    if (state.usage) {
        instance.lastUsage = state.usage;
    }

    // Update UI state
    setUiState(instance, 'idle');

    // Fire completion events
    instance.element.dispatchEvent(new CustomEvent('wpaic:stream-end', {
        detail: {
            content: state.content,
            finishReason: state.finishReason,
            usage: state.usage,
        },
        bubbles: true,
    }));

    instance.element.dispatchEvent(new CustomEvent('wpaic:response-received', {
        detail: {
            content: state.content,
            streamed: true,
            finishReason: state.finishReason,
            usage: state.usage,
            suggestions: suggestions,
        },
        bubbles: true,
    }));

    scrollToBottom(instance);
}
```

---

## 10. Custom Events Summary

| Event | When Fired | Detail Payload | Cancellable |
|-------|------------|---------------|-------------|
| `wpaic:stream-start` | First content chunk received | `{}` | No |
| `wpaic:stream-chunk` | Each content chunk | `{content, accumulated}` | No |
| `wpaic:tool-call` | Each tool_call chunk | `{toolCall}` | No |
| `wpaic:stream-end` | Done chunk received | `{content, finishReason, usage}` | No |
| `wpaic:tool-pending` | finish_reason is tool_calls | `{toolCalls}` | Yes (prevent default skips server-side execution) |
| `wpaic:response-received` | Stream fully finalized | `{content, streamed, finishReason, usage, suggestions}` | No |
| `wpaic:error` | Error during stream | `{error, partialContent, streamed}` | No |

---

## 11. Backend Stream Endpoint Registration

### 11.1 Phase 1: Stream Init (POST)

```php
add_action( 'wp_ajax_wpaic_chat_init_stream', 'wpaic_handle_chat_init_stream' );
add_action( 'wp_ajax_nopriv_wpaic_chat_init_stream', 'wpaic_handle_chat_init_stream' );
```

This endpoint:
1. Validates nonce, input, session, ownership (same as sync chat pipeline Stage 1)
2. Runs rate limiting, honeypot, moderation checks
3. Stores the validated message in a transient with a one-time stream token
4. Returns `{ success: true, data: { stream_token: '...' } }`

**Stream token**: UUID v4, stored as transient `wpaic_stream_{token}` with 60-second TTL. Contains the validated message, session ID, bot config, and assembled context. Consumed and deleted on first use.

### 11.2 Phase 2: Stream Delivery (GET)

```php
add_action( 'wp_ajax_wpaic_chat_stream', 'wpaic_handle_chat_stream' );
add_action( 'wp_ajax_nopriv_wpaic_chat_stream', 'wpaic_handle_chat_stream' );
```

This endpoint:
1. Validates the `stream_token` query parameter
2. Retrieves and deletes the transient (one-time use)
3. Sets SSE headers, cleans output buffers
4. Invokes `$provider->stream_chat()` with the callback
5. Performs post-stream processing
6. Calls `exit`

### 11.3 Security Considerations

- **One-time token**: The stream token is deleted from transients after first use, preventing replay attacks
- **60-second TTL**: Tokens expire quickly, limiting the window for token theft
- **No message in URL**: The user message is stored server-side (in the transient), not exposed in the EventSource URL. This prevents message leakage via browser history, server access logs, or referrer headers
- **AJAX nonce**: Validated in Phase 1, not required in Phase 2 (the stream token serves as the auth credential)
- **Same-origin cookies**: `withCredentials: true` on EventSource ensures WordPress auth cookies are sent

---

## 12. Testability Notes

### Unit Testable

- Chunk type dispatch logic (given parsed chunk, verify correct handler called)
- Content accumulation (sequential content chunks build complete string)
- Tool call accumulation (incremental argument deltas produce complete JSON)
- Finish reason detection and branching
- Streaming decision logic (`shouldStream()`)
- Stream token generation and validation (PHP)

### Integration Testable

- Full stream: PHP emits SSE events -> browser EventSource consumes -> DOM updates
- Phase 1 + Phase 2 handoff: POST init returns token, GET stream uses token
- Error mid-stream: partial content preserved + error displayed
- Timeout: stalled stream detected by frontend timer
- Fallback: streaming failure triggers sync AJAX
- Tool continuation: stream ends with `tool_calls`, continuation request sent
- One-time token: second use of same token returns error

### Not Testable in Isolation

- CDN/proxy buffering behavior (requires live infrastructure)
- Browser-level EventSource connection management
- PHP output buffering interactions (varies by server configuration)
- `set_time_limit()` behavior (may be disabled by hosting provider)

---
