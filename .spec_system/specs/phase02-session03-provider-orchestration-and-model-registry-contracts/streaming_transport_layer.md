# Streaming Transport Layer

**Session**: `phase02-session03-provider-orchestration-and-model-registry-contracts`
**Version**: 1.0
**Created**: 2026-03-01
**Resolves**: CFI-PRO-001 (SSE streaming), CFI-RUN-003 (provider-runtime boundary)

---

## 1. Overview

This document specifies the SSE (Server-Sent Events) streaming transport layer that connects provider adapters to the chat runtime. It defines the normalized chunk format, per-provider chunk parsing delegation, error signaling during streams, connection lifecycle management, and PHP output buffering requirements.

The design integrates MxChat's cURL WRITEFUNCTION streaming approach into the AxiaChat adapter architecture. The key innovation is separating the provider-side streaming contract (this document + `Streamable_Provider_Interface`) from the runtime-side consumption contract (session 04), breaking the CFI-PRO-001/CFI-RUN-003 circular dependency.

### Reference Anchors

| Source Plugin | File | Relevance |
|---------------|------|-----------|
| MxChat | `mxchat-basic/includes/class-mxchat-integrator.php` | Primary: SSE streaming via cURL WRITEFUNCTION, per-provider chunk parsing (OpenAI `delta.content`, Claude `content_block_delta`, Gemini `candidates[].content`), output buffering flush, `data: [DONE]` terminator |
| AxiaChat | `axiachat-ai/includes/providers/class-openai-provider.php` | Non-streaming reference (sync-only chat, response structure) |
| AxiaChat | `axiachat-ai/includes/providers/class-claude-provider.php` | Non-streaming reference (retry/fallback patterns) |
| Baachal | `baachal/baachal.php` | N/A -- no streaming support |

---

## 2. SSE Protocol Specification

### Wire Format

All streaming responses use standard SSE format as defined in the HTML Living Standard:

```
data: {"type":"content","content":"Hello"}\n\n
data: {"type":"content","content":" world"}\n\n
data: {"type":"usage","usage":{"prompt_tokens":10,"completion_tokens":5,"total_tokens":15}}\n\n
data: {"type":"done"}\n\n
```

**Rules**:
- Each event is a JSON-encoded object on a single `data:` line
- Events are terminated by two newline characters (`\n\n`)
- No `event:` field is used (all events are `message` type); type is in the JSON payload
- The stream terminates with `data: {"type":"done"}\n\n`
- No `data: [DONE]` format (OpenAI-specific); we normalize to JSON `{"type":"done"}`

### HTTP Headers

Provider adapters that directly output SSE (in the `stream_chat()` method) must NOT set HTTP headers. The runtime layer (session 04) is responsible for setting response headers before invoking `stream_chat()`:

```
Content-Type: text/event-stream; charset=UTF-8
Cache-Control: no-cache, no-store, must-revalidate
Connection: keep-alive
X-Accel-Buffering: no
```

### PHP Output Buffering

The runtime layer (not the adapter) handles output buffering cleanup before streaming begins:

```php
// Runtime responsibility (session 04):
while ( ob_get_level() ) {
    ob_end_flush();
}

// Disable implicit flush
if ( function_exists( 'apache_setenv' ) ) {
    apache_setenv( 'no-gzip', '1' );
}

ini_set( 'zlib.output_compression', '0' );
```

Adapters MUST NOT call `ob_end_flush()` or set headers. They only emit chunks via the `$callback` callable.

---

## 3. Normalized Chunk Format

Every chunk emitted by provider adapters via the `$callback` callable follows this normalized structure. The runtime layer consumes these chunks uniformly regardless of the upstream provider.

### Chunk Types

| Type | Purpose | Required Fields | Optional Fields |
|------|---------|----------------|-----------------|
| `content` | Text delta from model | `type`, `content` | - |
| `tool_call` | Tool/function call delta | `type`, `tool_call` | - |
| `usage` | Final token usage data | `type`, `usage` | - |
| `error` | Error during stream | `type`, `error` | - |
| `done` | Stream termination signal | `type` | `finish_reason` |

### Chunk Schemas

#### Content Chunk

```php
array(
    'type'    => 'content',
    'content' => 'Hello',  // string: Delta text content
)
```

Emitted for each text fragment received from the provider. The runtime concatenates these into the full response.

#### Tool Call Chunk

```php
array(
    'type'      => 'tool_call',
    'tool_call' => array(
        'index'    => 0,                        // int: Tool call index (for multiple concurrent calls)
        'id'       => 'call_abc123',            // string: Tool call ID (first chunk only, empty on subsequent)
        'function' => array(
            'name'      => 'get_weather',       // string: Function name (first chunk only, empty on subsequent)
            'arguments' => '{"location":',      // string: Arguments delta (accumulated by runtime)
        ),
    ),
)
```

Tool call chunks stream the function name and arguments incrementally. The runtime accumulates the `arguments` deltas and JSON-parses the complete string when `done` is received.

#### Usage Chunk

```php
array(
    'type'  => 'usage',
    'usage' => array(
        'prompt_tokens'     => 150,    // int: Input tokens consumed
        'completion_tokens' => 42,     // int: Output tokens generated
        'total_tokens'      => 192,    // int: Sum of prompt + completion
    ),
)
```

Emitted once, typically just before the `done` chunk. Some providers include usage in the final SSE event; others require a separate API call. Adapters MUST emit this chunk with whatever data is available.

#### Error Chunk

```php
array(
    'type'  => 'error',
    'error' => array(
        'code'         => 500,
        'type'         => 'model_error',
        'message'      => 'Internal server error from provider.',
        'retryable'    => false,
        'user_message' => 'An error occurred while generating the response. Please try again.',
    ),
)
```

Emitted when an error occurs mid-stream (after content has already been sent). The error payload follows the standard error schema from `error_handling_fallback_contract.md`. After emitting an error chunk, the adapter MUST emit a `done` chunk and return.

#### Done Chunk

```php
array(
    'type'          => 'done',
    'finish_reason' => 'stop',  // string: Optional canonical finish reason
)
```

The terminal chunk. MUST be the last chunk emitted. The runtime uses this as the signal to finalize the response.

---

## 4. Per-Provider Chunk Parsing

Each adapter is responsible for parsing its provider's native SSE format and normalizing chunks into the format above. This section documents the native formats for reference.

### OpenAI

**Native SSE format**:
```
data: {"id":"chatcmpl-xxx","object":"chat.completion.chunk","choices":[{"index":0,"delta":{"content":"Hello"},"finish_reason":null}]}

data: [DONE]
```

**Parsing rules**:
- Skip lines that don't start with `data: `
- Skip `data: [DONE]` (emit `done` chunk instead)
- Extract `choices[0].delta.content` for content chunks
- Extract `choices[0].delta.tool_calls` for tool call chunks
- Extract `choices[0].finish_reason` (non-null) for done signal
- Usage may appear in the final chunk or require a separate field

### Claude (Anthropic)

**Native SSE format**:
```
event: content_block_delta
data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"Hello"}}

event: message_delta
data: {"type":"message_delta","delta":{"stop_reason":"end_turn"},"usage":{"output_tokens":42}}

event: message_stop
data: {"type":"message_stop"}
```

**Parsing rules**:
- Parse `event:` line to determine event type
- `content_block_delta` with `delta.type === 'text_delta'`: emit content chunk with `delta.text`
- `content_block_delta` with `delta.type === 'input_json_delta'`: emit tool_call chunk with delta
- `message_delta`: extract `usage.output_tokens` for usage chunk, `stop_reason` for finish_reason
- `message_start`: extract `usage.input_tokens` for prompt_tokens
- `message_stop`: emit done chunk

**Stop reason mapping**:
| Claude | Canonical |
|--------|-----------|
| `end_turn` | `stop` |
| `max_tokens` | `length` |
| `tool_use` | `tool_calls` |

### Gemini

**Native SSE format** (with `?alt=sse`):
```
data: {"candidates":[{"content":{"parts":[{"text":"Hello"}]},"finishReason":"STOP"}],"usageMetadata":{"promptTokenCount":10,"candidatesTokenCount":5}}
```

**Parsing rules**:
- Each `data:` line contains a complete response fragment
- Extract `candidates[0].content.parts[0].text` for content chunks
- Extract `candidates[0].content.parts[].functionCall` for tool call chunks
- Extract `candidates[0].finishReason` for done signal
- Extract `usageMetadata` for usage chunk

**Finish reason mapping**:
| Gemini | Canonical |
|--------|-----------|
| `STOP` | `stop` |
| `MAX_TOKENS` | `length` |
| `TOOL_CODE` | `tool_calls` |
| `SAFETY` | `content_filter` |

### DeepSeek

**Native SSE format**: OpenAI-compatible (same parsing as OpenAI).

### Grok (xAI)

**Native SSE format**: OpenAI-compatible (same parsing as OpenAI).

---

## 5. Connection Lifecycle

### Lifecycle States

```
IDLE --> CONNECTING --> STREAMING --> FINALIZING --> COMPLETE
                  \                       |
                   \                      v
                    +---> ERROR --------> COMPLETE
```

| State | Description | Adapter Action |
|-------|-------------|---------------|
| IDLE | Before `stream_chat()` called | N/A |
| CONNECTING | HTTP connection being established | cURL init, headers sent |
| STREAMING | Receiving and parsing chunks | Emit content/tool_call chunks via callback |
| FINALIZING | Last chunk received, computing summary | Emit usage and done chunks |
| COMPLETE | Stream ended, summary returned | Return summary array from `stream_chat()` |
| ERROR | Error occurred at any stage | Emit error chunk, then done chunk, return error summary |

### Timeout Management

| Timeout | Value | Enforced By | Behavior on Expiry |
|---------|-------|-------------|-------------------|
| Connect timeout | 5 seconds | Adapter (cURL `CURLOPT_CONNECTTIMEOUT`) | Emit error chunk, return |
| Stream timeout | 120 seconds | Runtime (session 04) | Kill connection, emit error chunk |
| Chunk timeout | 30 seconds | Adapter (cURL `CURLOPT_LOW_SPEED_TIME`) | Emit error chunk, return |

**Chunk timeout**: If no data is received for 30 seconds during an active stream, the adapter treats it as a stall and terminates with an error chunk. This prevents indefinite hangs from provider-side issues.

### Connection Cleanup

Adapters MUST release all resources when `stream_chat()` returns:
- Close cURL handles (`curl_close()`)
- Unset large buffers
- Do NOT close output buffers (runtime responsibility)

---

## 6. cURL WRITEFUNCTION Pattern

The recommended implementation pattern for adapters, adopted from MxChat:

```php
// Pseudocode -- actual implementation in Phase 04 adapters
public function stream_chat( $messages, $params = array(), $callback = null ) {
    $url     = $this->build_stream_url( $params['model'] );
    $headers = $this->build_headers();
    $body    = $this->build_stream_body( $messages, $params );
    $buffer  = '';
    $summary = array( 'message' => '', 'usage' => array(), 'tool_calls' => array() );

    $ch = curl_init( $url );
    curl_setopt_array( $ch, array(
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => wp_json_encode( $body ),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_LOW_SPEED_LIMIT => 1,
        CURLOPT_LOW_SPEED_TIME  => 30,
        CURLOPT_WRITEFUNCTION  => function( $ch, $data ) use ( $callback, &$buffer, &$summary ) {
            $buffer .= $data;
            // Parse complete SSE events from buffer
            while ( false !== ( $pos = strpos( $buffer, "\n\n" ) ) ) {
                $event = substr( $buffer, 0, $pos );
                $buffer = substr( $buffer, $pos + 2 );
                $chunk = $this->parse_provider_chunk( $event );
                if ( null !== $chunk ) {
                    $this->accumulate_summary( $summary, $chunk );
                    if ( null !== $callback ) {
                        call_user_func( $callback, $chunk );
                    }
                }
            }
            return strlen( $data );
        },
    ) );

    $result = curl_exec( $ch );
    $error  = curl_error( $ch );
    $code   = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    curl_close( $ch );

    if ( false === $result || ! empty( $error ) ) {
        $error_chunk = $this->build_error_chunk( $code, $error );
        if ( null !== $callback ) {
            call_user_func( $callback, $error_chunk );
            call_user_func( $callback, array( 'type' => 'done' ) );
        }
        $summary['error'] = $error_chunk['error'];
    }

    return $summary;
}
```

### Why cURL Instead of wp_remote_post()

WordPress `wp_remote_post()` does not support streaming callbacks. The cURL WRITEFUNCTION approach is the only way to process SSE chunks as they arrive in PHP. This is an accepted pattern in WordPress plugin development for streaming use cases.

---

## 7. Error Signaling During Streams

### Pre-Stream Errors

Errors that occur before any content is sent (connection failure, auth error, etc.) follow the standard error response path -- no SSE is involved. The adapter's `stream_chat()` returns an error summary array directly.

### Mid-Stream Errors

Errors that occur after content chunks have been emitted:

1. Adapter detects error (connection drop, provider error event, timeout)
2. Emit error chunk via `$callback`:
   ```php
   call_user_func( $callback, array(
       'type'  => 'error',
       'error' => array(
           'code'         => 500,
           'type'         => 'network',
           'message'      => 'Connection to provider lost during streaming.',
           'retryable'    => false,
           'user_message' => 'The response was interrupted. Please try again.',
       ),
   ) );
   ```
3. Emit done chunk: `call_user_func( $callback, array( 'type' => 'done' ) )`
4. Return summary with partial content + error

### Mid-Stream Error Policy

Mid-stream errors are NOT retried or fallen back. Once content has been emitted to the client, retrying would produce duplicate/inconsistent output. The partial response is preserved, and the error is appended.

---

## 8. Interface Boundary Summary (CFI-PRO-001 / CFI-RUN-003)

This section explicitly defines the provider-runtime boundary to resolve the circular dependency:

### Provider Side (This Session)

| Responsibility | Contract |
|---------------|----------|
| Open HTTP connection to provider API | Adapter `stream_chat()` |
| Parse provider-native SSE chunks | Adapter per-provider parsing |
| Normalize chunks to unified format | Adapter emits via `$callback` |
| Detect and signal errors | Adapter emits error + done chunks |
| Return stream summary | Adapter `stream_chat()` return value |
| Release resources (cURL handles) | Adapter cleanup on return |

### Runtime Side (Session 04)

| Responsibility | Contract |
|---------------|----------|
| Set HTTP response headers (SSE) | Runtime before calling `stream_chat()` |
| Disable output buffering | Runtime before calling `stream_chat()` |
| Provide `$callback` to adapter | Runtime injects callback |
| Emit SSE to browser from callback | Runtime `$callback` implementation |
| Accumulate response state | Runtime tracks content, usage, tool_calls |
| Enforce stream timeout | Runtime monitors elapsed time |
| Handle stream completion | Runtime processes summary from `stream_chat()` |
| Persist conversation (messages, usage) | Runtime after stream completes |

### Contract: Neither Side Depends on the Other's Implementation

- Providers only know about the `$callback` signature -- they don't know how chunks reach the browser
- Runtime only knows about the chunk schema -- it doesn't know how chunks are parsed from providers
- Both sides can be implemented and tested independently

---

## 9. Testability Notes

### Unit Testable

- Per-provider chunk parsing (given raw SSE string, output normalized chunk)
- Chunk schema validation (required fields per type)
- Summary accumulation (content concatenation, usage merging)
- Error chunk construction
- Buffer parsing (partial SSE events, multi-event buffers)

### Integration Testable

- Full `stream_chat()` flow with mock cURL responses
- Callback invocation sequence (content chunks -> usage -> done)
- Error recovery (mid-stream disconnect -> error chunk -> done)
- Timeout detection (no data for 30 seconds)

### Not Testable in Isolation

- Actual SSE browser rendering (frontend responsibility, session 04)
- Real provider API streaming (requires live API keys)

---
