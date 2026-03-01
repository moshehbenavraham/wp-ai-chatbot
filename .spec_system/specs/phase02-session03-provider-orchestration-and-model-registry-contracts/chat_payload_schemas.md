# Chat Payload Schemas

**Session**: `phase02-session03-provider-orchestration-and-model-registry-contracts`
**Version**: 1.0
**Created**: 2026-03-01

---

## 1. Overview

This document defines the unified, provider-agnostic request and response payload schemas used throughout the plugin. All consumers (runtime, admin, tools) interact with a single normalized format. Individual provider adapters translate between this format and their native API format.

### Reference Anchors

| Source Plugin | File | Relevance |
|---------------|------|-----------|
| AxiaChat | `axiachat-ai/includes/providers/class-openai-provider.php` | Request construction (messages, params, tools) and response normalization (message, usage, finish_reason, tool_calls) |
| AxiaChat | `axiachat-ai/includes/providers/class-claude-provider.php` | System prompt as top-level param, content blocks, stop_reason mapping |
| AxiaChat | `axiachat-ai/includes/providers/class-gemini-provider.php` | Parts format, systemInstruction, generationConfig, usageMetadata |
| MxChat | `mxchat-basic/includes/class-mxchat-integrator.php` | 5-provider dispatch, per-provider message formatting, SSE chunk parsing |
| Baachal | `baachal/baachal.php` | Minimal request format (messages + model), basic response extraction |

---

## 2. Unified Chat Request Payload

The canonical request format used by all runtime consumers. The provider adapter translates this into the provider's native format.

### Schema

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "title": "WPAIC Chat Request",
  "type": "object",
  "required": ["messages", "model"],
  "properties": {
    "messages": {
      "type": "array",
      "description": "Conversation message history in chronological order.",
      "minItems": 1,
      "items": {
        "type": "object",
        "required": ["role", "content"],
        "properties": {
          "role": {
            "type": "string",
            "enum": ["system", "user", "assistant", "tool"],
            "description": "Message author role."
          },
          "content": {
            "description": "Message content. String for text-only, array for multimodal.",
            "oneOf": [
              { "type": "string" },
              {
                "type": "array",
                "items": {
                  "type": "object",
                  "required": ["type"],
                  "properties": {
                    "type": {
                      "type": "string",
                      "enum": ["text", "image_url"]
                    },
                    "text": { "type": "string" },
                    "image_url": {
                      "type": "object",
                      "properties": {
                        "url": { "type": "string", "format": "uri" }
                      }
                    }
                  }
                }
              }
            ]
          },
          "tool_call_id": {
            "type": "string",
            "description": "ID of the tool call this message responds to (role='tool' only)."
          },
          "name": {
            "type": "string",
            "description": "Tool function name (role='tool' only)."
          }
        }
      }
    },
    "model": {
      "type": "string",
      "description": "Model ID. Resolved by model registry before dispatch (see model_registry_data_model.md)."
    },
    "temperature": {
      "type": "number",
      "minimum": 0.0,
      "maximum": 2.0,
      "default": 0.7,
      "description": "Sampling temperature. Lower = more deterministic."
    },
    "max_tokens": {
      "type": "integer",
      "minimum": 1,
      "maximum": 128000,
      "default": 2048,
      "description": "Maximum tokens in the response."
    },
    "tools": {
      "type": "array",
      "description": "Tool/function definitions available to the model.",
      "items": {
        "type": "object",
        "required": ["type", "function"],
        "properties": {
          "type": {
            "type": "string",
            "const": "function"
          },
          "function": {
            "type": "object",
            "required": ["name", "description"],
            "properties": {
              "name": {
                "type": "string",
                "description": "Function name (alphanumeric + underscores)."
              },
              "description": {
                "type": "string",
                "description": "What the function does."
              },
              "parameters": {
                "type": "object",
                "description": "JSON Schema for function arguments."
              }
            }
          }
        }
      }
    },
    "stream": {
      "type": "boolean",
      "default": false,
      "description": "Whether to use SSE streaming. Requires Streamable_Provider_Interface."
    },
    "system_prompt": {
      "type": "string",
      "description": "System prompt. Takes precedence over any system message in the messages array. Adapters merge this appropriately per provider format."
    },
    "metadata": {
      "type": "object",
      "description": "Arbitrary key-value metadata passed through the request lifecycle.",
      "properties": {
        "request_id": {
          "type": "string",
          "description": "Unique request UUID for tracing and correlation."
        },
        "session_id": {
          "type": "string",
          "description": "Chat session ID for conversation continuity."
        },
        "bot_slug": {
          "type": "string",
          "description": "Bot configuration slug."
        },
        "user_id": {
          "type": "integer",
          "description": "WordPress user ID (0 for anonymous)."
        }
      },
      "additionalProperties": true
    }
  }
}
```

### Field Details

#### messages

The conversation history array. Each message has a `role` and `content`.

**Role definitions**:

| Role | Description | Content Type | When Used |
|------|-------------|-------------|-----------|
| `system` | System instructions | String only | First message (or use `system_prompt` field) |
| `user` | User input | String or multimodal array | Every user turn |
| `assistant` | AI response | String only | Previous AI responses in history |
| `tool` | Tool execution result | String (JSON-serialized result) | After tool call execution |

**Multimodal content** (user messages only):

```php
// Text-only message
array( 'role' => 'user', 'content' => 'Hello, how are you?' )

// Multimodal message with image
array(
    'role'    => 'user',
    'content' => array(
        array( 'type' => 'text', 'text' => 'What is in this image?' ),
        array( 'type' => 'image_url', 'image_url' => array( 'url' => 'https://example.com/photo.jpg' ) ),
    ),
)
```

#### system_prompt

Optional top-level system prompt that takes precedence over system-role messages in the `messages` array. Adapters handle placement:

| Provider | system_prompt Handling |
|----------|----------------------|
| OpenAI | Prepended as `role: system` message (or merged with existing) |
| Claude | Set as `system` top-level API parameter |
| Gemini | Set as `systemInstruction` API parameter |
| DeepSeek | Prepended as `role: system` message |
| Grok | Prepended as `role: system` message |

#### tools

Tool definitions follow the OpenAI function calling format as the canonical schema. Adapters translate to provider-native format:

| Provider | Tool Format Translation |
|----------|------------------------|
| OpenAI | Pass through (native format) |
| Claude | Convert to `{ name, description, input_schema }` |
| Gemini | Convert to `functionDeclarations` within `tools[]` |
| DeepSeek | Pass through (OpenAI-compatible) |
| Grok | Pass through (OpenAI-compatible) |

### Provider Request Mapping Table

Complete field mapping from unified request to each provider's native API format:

| Unified Field | OpenAI | Claude | Gemini | DeepSeek | Grok |
|--------------|--------|--------|--------|----------|------|
| `messages` | `messages` (pass-through) | `messages` (remove system, remap roles) | `contents[].parts[]` (convert format) | `messages` (pass-through) | `messages` (pass-through) |
| `model` | `model` (body param) | `model` (body param) | URL path: `/models/{model}:generateContent` | `model` (body param) | `model` (body param) |
| `temperature` | `temperature` | `temperature` | `generationConfig.temperature` | `temperature` | `temperature` |
| `max_tokens` | `max_tokens` | `max_tokens` | `generationConfig.maxOutputTokens` | `max_tokens` | `max_tokens` |
| `system_prompt` | Prepend as system message | `system` (top-level string) | `systemInstruction.parts[].text` | Prepend as system message | Prepend as system message |
| `tools` | `tools` (native) | `tools` (converted) | `tools[].functionDeclarations` (converted) | `tools` (native) | `tools` (native) |
| `stream` | `"stream": true` | `"stream": true` | `?alt=sse` query param | `"stream": true` | `"stream": true` |
| `metadata.request_id` | Not sent to API | Not sent to API | Not sent to API | Not sent to API | Not sent to API |

---

## 3. Unified Chat Response Payload

The canonical response format returned by all provider adapters. Consumers never see provider-native response formats.

### Success Response Schema

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "title": "WPAIC Chat Response (Success)",
  "type": "object",
  "required": ["message", "usage", "finish_reason", "provider_id", "model_id", "request_id"],
  "properties": {
    "message": {
      "type": "string",
      "description": "Assistant response text content."
    },
    "usage": {
      "type": "object",
      "required": ["prompt_tokens", "completion_tokens", "total_tokens"],
      "properties": {
        "prompt_tokens": {
          "type": "integer",
          "minimum": 0,
          "description": "Input tokens consumed."
        },
        "completion_tokens": {
          "type": "integer",
          "minimum": 0,
          "description": "Output tokens generated."
        },
        "total_tokens": {
          "type": "integer",
          "minimum": 0,
          "description": "Sum of prompt + completion tokens."
        }
      }
    },
    "finish_reason": {
      "type": "string",
      "enum": ["stop", "length", "tool_calls", "content_filter"],
      "description": "Why the model stopped generating."
    },
    "tool_calls": {
      "type": "array",
      "description": "Tool/function calls requested by the model (present when finish_reason='tool_calls').",
      "items": {
        "type": "object",
        "required": ["id", "type", "function"],
        "properties": {
          "id": {
            "type": "string",
            "description": "Unique tool call ID for correlation with tool results."
          },
          "type": {
            "type": "string",
            "const": "function"
          },
          "function": {
            "type": "object",
            "required": ["name", "arguments"],
            "properties": {
              "name": {
                "type": "string",
                "description": "Function name to execute."
              },
              "arguments": {
                "type": "string",
                "description": "JSON-encoded function arguments."
              }
            }
          }
        }
      }
    },
    "provider_id": {
      "type": "string",
      "description": "Canonical provider ID (e.g., 'openai', 'claude')."
    },
    "model_id": {
      "type": "string",
      "description": "Actual model ID used (may differ from requested if fallback occurred)."
    },
    "request_id": {
      "type": "string",
      "description": "Request UUID for tracing and correlation."
    }
  }
}
```

### Error Response Schema

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "title": "WPAIC Chat Response (Error)",
  "type": "object",
  "required": ["error"],
  "properties": {
    "error": {
      "type": "object",
      "required": ["code", "type", "message", "retryable", "user_message"],
      "properties": {
        "code": {
          "type": "integer",
          "description": "HTTP status code or internal error code."
        },
        "type": {
          "type": "string",
          "enum": ["network", "authentication", "rate_limit", "model_error"],
          "description": "Error taxonomy category (see error_handling_fallback_contract.md)."
        },
        "message": {
          "type": "string",
          "description": "Technical error message for logging. May contain diagnostic details."
        },
        "retryable": {
          "type": "boolean",
          "description": "Whether the caller should retry this request."
        },
        "user_message": {
          "type": "string",
          "description": "User-friendly error message safe for display in the chat widget."
        }
      }
    },
    "provider_id": {
      "type": "string",
      "description": "Provider that produced the error (if known)."
    },
    "model_id": {
      "type": "string",
      "description": "Model that was attempted (if known)."
    },
    "request_id": {
      "type": "string",
      "description": "Request UUID for correlation."
    }
  }
}
```

### Field Presence Rules by Finish Reason

| Field | `stop` | `length` | `tool_calls` | `content_filter` | Error |
|-------|--------|----------|-------------|------------------|-------|
| `message` | Required (full response) | Required (truncated) | Optional (may be empty) | Required (partial or empty) | Absent |
| `usage` | Required | Required | Required | Required | Absent |
| `finish_reason` | `"stop"` | `"length"` | `"tool_calls"` | `"content_filter"` | Absent |
| `tool_calls` | Absent | Absent | Required (non-empty) | Absent | Absent |
| `error` | Absent | Absent | Absent | Absent | Required |
| `provider_id` | Required | Required | Required | Required | Optional |
| `model_id` | Required | Required | Required | Required | Optional |
| `request_id` | Required | Required | Required | Required | Optional |

### Provider Response Mapping Table

How each provider's native response maps to the unified format:

| Unified Field | OpenAI Source | Claude Source | Gemini Source | DeepSeek Source | Grok Source |
|--------------|-------------|-------------|--------------|----------------|-------------|
| `message` | `choices[0].message.content` | `content[]` text blocks joined | `candidates[0].content.parts[0].text` | `choices[0].message.content` | `choices[0].message.content` |
| `usage.prompt_tokens` | `usage.prompt_tokens` | `usage.input_tokens` | `usageMetadata.promptTokenCount` | `usage.prompt_tokens` | `usage.prompt_tokens` |
| `usage.completion_tokens` | `usage.completion_tokens` | `usage.output_tokens` | `usageMetadata.candidatesTokenCount` | `usage.completion_tokens` | `usage.completion_tokens` |
| `finish_reason` | `choices[0].finish_reason` | `stop_reason` (mapped) | `candidates[0].finishReason` (mapped) | `choices[0].finish_reason` | `choices[0].finish_reason` |
| `tool_calls` | `choices[0].message.tool_calls` | `content[].tool_use` blocks (converted) | `candidates[0].content.parts[].functionCall` (converted) | `choices[0].message.tool_calls` | `choices[0].message.tool_calls` |

### Tool Call Normalization

All tool calls are normalized to the OpenAI format as the canonical schema:

```php
// Canonical tool call format (OpenAI-native)
array(
    'id'       => 'call_abc123',
    'type'     => 'function',
    'function' => array(
        'name'      => 'get_weather',
        'arguments' => '{"location": "San Francisco"}',
    ),
)
```

**Claude tool_use block to canonical**:

```php
// Claude native:    { type: 'tool_use', id: 'toolu_xxx', name: 'get_weather', input: { location: 'SF' } }
// Canonical output: { id: 'toolu_xxx', type: 'function', function: { name: 'get_weather', arguments: '{"location":"SF"}' } }
```

**Gemini functionCall to canonical**:

```php
// Gemini native:    { functionCall: { name: 'get_weather', args: { location: 'SF' } } }
// Canonical output: { id: 'call_gemini_xxx', type: 'function', function: { name: 'get_weather', arguments: '{"location":"SF"}' } }
// Note: Gemini does not provide tool call IDs -- adapter generates a unique ID with 'call_gemini_' prefix.
```

---

## 4. PHP Usage Examples

### Constructing a Request

```php
$request = array(
    'messages'      => array(
        array( 'role' => 'user', 'content' => 'What is the weather in Tokyo?' ),
    ),
    'model'         => 'gpt-4o',
    'temperature'   => 0.7,
    'max_tokens'    => 2048,
    'system_prompt' => 'You are a helpful assistant.',
    'metadata'      => array(
        'request_id' => wp_generate_uuid4(),
        'session_id' => $session_id,
        'bot_slug'   => 'default',
    ),
);
```

### Consuming a Response

```php
$response = $adapter->chat( $request['messages'], $request );

if ( isset( $response['error'] ) ) {
    // Error path
    $error = $response['error'];
    if ( $error['retryable'] ) {
        // Retry logic handled by orchestration layer
    }
    // Display $error['user_message'] to user
    return;
}

// Success path
$message       = $response['message'];
$tokens_used   = $response['usage']['total_tokens'];
$finish_reason = $response['finish_reason'];

if ( 'tool_calls' === $finish_reason ) {
    // Execute tool calls and continue conversation
    foreach ( $response['tool_calls'] as $tool_call ) {
        // Execute tool and append result as role='tool' message
    }
}
```

---

## 5. Testability Notes

### Unit Testable

- Request schema validation (required fields, types, ranges)
- Response schema validation (field presence rules by finish_reason)
- Tool call normalization (Claude tool_use to canonical, Gemini functionCall to canonical)
- Finish reason mapping (per-provider to canonical)
- Multimodal content array structure validation

### Integration Testable

- Round-trip: construct request -> adapter.chat() -> validate response shape
- Tool call flow: request with tools -> tool_calls response -> tool result message -> final response
- Streaming: request with stream=true -> callback chunks -> final summary matches sync format

---
