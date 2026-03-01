# Provider Interface Contract

**Session**: `phase02-session03-provider-orchestration-and-model-registry-contracts`
**Version**: 1.0
**Created**: 2026-03-01

---

## 1. Overview

This document defines the PHP interface contracts that every AI provider adapter must implement. The design adopts AxiaChat's `AIChat_Provider_Interface` as the winning pattern (DEC-PRO-001), extends it with a `Streamable_Provider_Interface` for SSE-capable adapters (resolving CFI-PRO-001), and formalizes compliance rules the provider registry enforces at registration time.

All interfaces reside in the `WPAIC\Provider` namespace under `includes/provider/`.

### Reference Anchors

| Source Plugin | File | Relevance |
|---------------|------|-----------|
| AxiaChat | `axiachat-ai/includes/interfaces/interface-aichat-provider.php` | Primary: Interface pattern adopted (4 methods, doc-block contracts) |
| AxiaChat | `axiachat-ai/includes/providers/class-openai-provider.php` | Adapter implementation exemplar (dual-API, tool calling, retry) |
| AxiaChat | `axiachat-ai/includes/providers/class-claude-provider.php` | Retry/fallback exemplar, tool state persistence |
| AxiaChat | `axiachat-ai/includes/providers/class-gemini-provider.php` | Multi-modal, thinking mode, native tools |
| MxChat | `mxchat-basic/includes/class-mxchat-integrator.php` | SSE streaming via cURL WRITEFUNCTION (N/A for interface -- no interface defined) |
| Baachal | `baachal/baachal.php` | User-friendly error messaging (N/A for interface -- no interface defined) |

---

## 2. Provider_Interface

The base interface for all synchronous AI provider adapters.

### PHP Signature

```php
<?php
namespace WPAIC\Provider;

/**
 * Provider_Interface
 *
 * Contract for AI provider adapters. Every provider registered with the
 * Provider_Registry must implement this interface.
 *
 * All adapters receive configuration via the constructor, expose a unique
 * provider ID, handle synchronous chat completion, and report cost in
 * microcents for usage tracking.
 *
 * @since 1.0.0
 * @package WPAIC\Provider
 */
interface Provider_Interface {

    /**
     * Construct the provider with configuration.
     *
     * The registry injects provider-specific configuration (API key,
     * endpoint overrides, timeouts) resolved from wp_options via the
     * Settings_Manager.
     *
     * Adapters MUST NOT call external APIs or register hooks in the
     * constructor. Use lazy initialization for expensive resources.
     *
     * @param array $config {
     *     Provider configuration array.
     *
     *     @type string $api_key     Decrypted API key (from Settings_Manager).
     *     @type string $endpoint    Optional endpoint override URL.
     *     @type int    $timeout     Request timeout in seconds (default: 30).
     *     @type int    $max_retries Maximum retry attempts (default: 3).
     * }
     */
    public function __construct( $config = array() );

    /**
     * Return the unique canonical provider identifier.
     *
     * Must be a lowercase alphanumeric slug matching the provider's
     * registration ID in the Provider_Registry. This value is stored
     * in database records (sessions, messages, usage) and MUST NOT change
     * once published.
     *
     * Canonical IDs: 'openai', 'claude', 'gemini', 'deepseek', 'grok'
     *
     * @return string Provider ID (lowercase, alphanumeric, hyphens allowed).
     */
    public function get_id();

    /**
     * Send a synchronous chat completion request.
     *
     * Accepts a normalized request payload (see chat_payload_schemas.md)
     * and returns a normalized response array. The adapter is responsible
     * for translating between the unified format and the provider's native
     * API format.
     *
     * On success, the response MUST contain 'message' and 'usage' keys.
     * On failure, the response MUST contain an 'error' key with the
     * standardized error payload (see error_handling_fallback_contract.md).
     *
     * Adapters MUST NOT catch and suppress errors silently. All failures
     * must surface through the error payload.
     *
     * @param array $messages Normalized message array. Each element:
     *     @type string $role    One of 'system', 'user', 'assistant', 'tool'.
     *     @type string $content Message content (string or structured array for multimodal).
     *     @type array  $tool_calls Optional tool call results (role='tool' only).
     *
     * @param array $params {
     *     Request parameters.
     *
     *     @type string $model        Model ID (resolved by model registry before dispatch).
     *     @type float  $temperature  Sampling temperature (0.0-2.0).
     *     @type int    $max_tokens   Maximum response tokens.
     *     @type array  $tools        Tool definitions array (optional).
     *     @type string $system_prompt System prompt override (optional).
     *     @type string $request_id   Unique request UUID for tracing.
     *     @type string $session_id   Chat session ID for context.
     *     @type array  $metadata     Arbitrary key-value metadata (optional).
     * }
     *
     * @return array {
     *     Normalized response payload.
     *
     *     Success response:
     *     @type string $message          Assistant response text.
     *     @type array  $usage {
     *         @type int $prompt_tokens     Input token count.
     *         @type int $completion_tokens Output token count.
     *         @type int $total_tokens      Sum of prompt + completion.
     *     }
     *     @type string $finish_reason     One of 'stop', 'length', 'tool_calls', 'content_filter'.
     *     @type array  $tool_calls        Tool call requests from model (optional).
     *     @type string $provider_id       Canonical provider ID.
     *     @type string $model_id          Actual model used (may differ from requested if fallback).
     *     @type string $request_id        Echo of input request_id for correlation.
     *
     *     Error response:
     *     @type array  $error {
     *         @type int    $code         HTTP status code or internal error code.
     *         @type string $type         Error taxonomy type (see error contract).
     *         @type string $message      Technical error message (for logging).
     *         @type bool   $retryable    Whether the caller should retry.
     *         @type string $user_message User-friendly error message (for display).
     *     }
     * }
     */
    public function chat( $messages, $params = array() );

    /**
     * Calculate request cost in microcents.
     *
     * Uses the model registry pricing data to compute cost based on
     * token usage. Returns null for free/local models or when pricing
     * data is unavailable.
     *
     * 1 microcent = 1/10,000 of a cent = 1/1,000,000 of a dollar.
     * Formula: (tokens / 1,000,000) * rate_per_1M * 100 * 10,000
     *
     * @param array $usage {
     *     Token usage from response.
     *
     *     @type int $prompt_tokens     Input tokens consumed.
     *     @type int $completion_tokens Output tokens generated.
     *     @type int $total_tokens      Total tokens (informational).
     * }
     * @param string $model The model ID used for the request.
     *
     * @return int|null Cost in microcents, or null if not calculable.
     */
    public function calculate_cost( $usage, $model );
}
```

### Compliance Rules

The Provider_Registry enforces these rules at registration time:

| Rule | Check | Failure Behavior |
|------|-------|------------------|
| CR-01 | Class implements `WPAIC\Provider\Provider_Interface` | Registration rejected, `_doing_it_wrong()` notice |
| CR-02 | `get_id()` returns non-empty string | Registration rejected |
| CR-03 | `get_id()` matches the registered ID | Registration rejected (prevents ID spoofing) |
| CR-04 | `get_id()` contains only `[a-z0-9-]` characters | Registration rejected |
| CR-05 | No duplicate ID registration (unless `force` flag) | Registration rejected, existing adapter preserved |

### Constructor Contract

- **MUST NOT** make HTTP requests or register WordPress hooks
- **MUST** store `$config` for later use by `chat()` and `calculate_cost()`
- **MUST** validate required config keys and set safe defaults for optional ones
- **MAY** perform lightweight validation (e.g., check API key is non-empty string)

---

## 3. Streamable_Provider_Interface

Extended interface for providers that support SSE streaming. Adapters that implement this interface signal to the runtime that streaming is available.

### PHP Signature

```php
<?php
namespace WPAIC\Provider;

/**
 * Streamable_Provider_Interface
 *
 * Extended contract for provider adapters that support Server-Sent Events
 * (SSE) streaming. Implementing this interface is optional -- adapters
 * that only support synchronous chat implement Provider_Interface alone.
 *
 * The runtime checks `$adapter instanceof Streamable_Provider_Interface`
 * to determine streaming availability. If streaming is requested but
 * the adapter does not implement this interface, the runtime falls back
 * to synchronous chat() automatically.
 *
 * Resolves: CFI-PRO-001 (SSE streaming boundary) and CFI-RUN-003
 * (circular dependency with runtime streaming layer).
 *
 * @since 1.0.0
 * @package WPAIC\Provider
 */
interface Streamable_Provider_Interface extends Provider_Interface {

    /**
     * Send a streaming chat completion request via SSE.
     *
     * The adapter is responsible for:
     * 1. Opening the HTTP connection to the provider API with streaming enabled
     * 2. Parsing provider-specific chunk formats into normalized SSE events
     * 3. Emitting each chunk via the $callback callable
     * 4. Returning a final summary array after the stream completes
     *
     * The $callback receives normalized chunk arrays with a consistent shape
     * regardless of the upstream provider format. See streaming_transport_layer.md
     * for the full chunk normalization contract.
     *
     * On error mid-stream, the adapter MUST call $callback with an error chunk
     * (type='error') and then return an error response array. The adapter MUST NOT
     * throw exceptions during streaming.
     *
     * @param array    $messages Normalized message array (same as Provider_Interface::chat()).
     * @param array    $params   Request parameters (same as Provider_Interface::chat()),
     *                           plus:
     *     @type callable $on_chunk Chunk callback (injected by runtime, see below).
     *
     * @param callable $callback Chunk emission callback. Signature:
     *     function( array $chunk ): void
     *
     *     $chunk structure:
     *     @type string $type    One of 'content', 'tool_call', 'usage', 'error', 'done'.
     *     @type string $content Delta text content (type='content' only).
     *     @type array  $tool_call Tool call delta (type='tool_call' only).
     *     @type array  $usage   Final usage data (type='usage' only).
     *     @type array  $error   Error payload (type='error' only).
     *
     * @return array {
     *     Stream completion summary.
     *
     *     @type string $message          Full concatenated response text.
     *     @type array  $usage            Final token usage counts.
     *     @type string $finish_reason    Final finish reason.
     *     @type array  $tool_calls       Complete tool call array (if any).
     *     @type string $provider_id      Canonical provider ID.
     *     @type string $model_id         Actual model used.
     *     @type string $request_id       Request UUID for correlation.
     *     @type array  $error            Error payload (if stream failed).
     * }
     */
    public function stream_chat( $messages, $params = array(), $callback = null );

    /**
     * Check whether streaming is currently available for this provider.
     *
     * Allows adapters to dynamically disable streaming based on
     * configuration, API key status, or model capabilities.
     *
     * @return bool True if streaming is available.
     */
    public function supports_streaming();
}
```

### Streaming Boundary Definition (CFI-PRO-001 / CFI-RUN-003 Resolution)

The interface boundary between provider and runtime is:

```
Provider Adapter (this contract)         Runtime (session 04 contract)
================================         ============================
stream_chat() opens connection    -->    Runtime calls stream_chat()
Parses provider chunks            -->    Runtime passes $callback
Normalizes to chunk array         -->    $callback emits SSE to client
Calls $callback(chunk)            -->    Runtime tracks accumulated state
Returns summary array             -->    Runtime finalizes response
```

- **Provider responsibility**: HTTP connection, chunk parsing, normalization, error detection
- **Runtime responsibility**: SSE output to browser, state accumulation, lifecycle management, timeout enforcement
- **Boundary**: The `$callback` callable is the contract boundary. Providers push normalized chunks; runtime consumes them.

### Streaming Compliance Rules

| Rule | Check | Failure Behavior |
|------|-------|------------------|
| SCR-01 | Class implements `Streamable_Provider_Interface` | Streaming unavailable, sync fallback |
| SCR-02 | `supports_streaming()` returns `true` | Streaming unavailable, sync fallback |
| SCR-03 | `stream_chat()` calls `$callback` at least once | Runtime logs warning, treats as empty response |
| SCR-04 | `stream_chat()` calls `$callback` with `type='done'` as final chunk | Runtime enforces timeout if missing |
| SCR-05 | `stream_chat()` never throws exceptions | Uncaught exceptions logged, stream terminated |

---

## 4. Provider Adapter Contract Template

Concrete requirements checklist for Phase 04 adapter implementors. Each adapter class MUST satisfy all items below.

### 4.1 Interface Compliance

| Requirement | Details |
|-------------|---------|
| Implements `Provider_Interface` | Required for all adapters |
| Implements `Streamable_Provider_Interface` | Required for OpenAI, Claude, Gemini, DeepSeek, Grok |
| Namespace | `WPAIC\Provider\Adapters` |
| Class naming | `{Provider}_Adapter` (e.g., `OpenAI_Adapter`, `Claude_Adapter`) |
| File naming | `class-{provider}-adapter.php` (e.g., `class-openai-adapter.php`) |
| File location | `includes/provider/adapters/` |

### 4.2 Constructor Config Shape

Each adapter receives this config array from the registry:

```php
$config = array(
    'api_key'     => '',    // string: Decrypted API key from Settings_Manager
    'endpoint'    => '',    // string: API endpoint URL (empty = use default)
    'timeout'     => 30,    // int: Request timeout in seconds
    'max_retries' => 3,     // int: Maximum retry attempts
);
```

Adapters MAY define additional provider-specific config keys via the `wpaic_provider_{id}_config` filter:

```php
$config = apply_filters( 'wpaic_provider_openai_config', $config );
```

### 4.3 API Endpoint URLs

| Provider | Default Endpoint | Auth Header |
|----------|-----------------|-------------|
| OpenAI | `https://api.openai.com/v1/chat/completions` | `Authorization: Bearer {key}` |
| Claude | `https://api.anthropic.com/v1/messages` | `x-api-key: {key}`, `anthropic-version: 2023-06-01` |
| Gemini | `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent` | `?key={key}` (query param) |
| DeepSeek | `https://api.deepseek.com/v1/chat/completions` | `Authorization: Bearer {key}` |
| Grok | `https://api.x.ai/v1/chat/completions` | `Authorization: Bearer {key}` |

### 4.4 Request Translation

Each adapter MUST translate the unified request payload (see `chat_payload_schemas.md`) into the provider's native API format. The translation mapping:

| Unified Field | OpenAI | Claude | Gemini | DeepSeek | Grok |
|--------------|--------|--------|--------|----------|------|
| `messages` | `messages` | `messages` (role remapping) | `contents` (parts format) | `messages` | `messages` |
| `model` | `model` | `model` | URL path param | `model` | `model` |
| `temperature` | `temperature` | `temperature` | `generationConfig.temperature` | `temperature` | `temperature` |
| `max_tokens` | `max_tokens` | `max_tokens` | `generationConfig.maxOutputTokens` | `max_tokens` | `max_tokens` |
| `system_prompt` | First message role=system | `system` top-level param | `systemInstruction` | First message role=system | First message role=system |
| `tools` | `tools` (OpenAI format) | `tools` (Anthropic format) | `tools` (Gemini format) | `tools` (OpenAI format) | `tools` (OpenAI format) |
| `stream` | `stream: true` | `stream: true` | `?alt=sse` query param | `stream: true` | `stream: true` |

### 4.5 Response Normalization

Each adapter MUST normalize the provider's native response into the unified response payload:

| Unified Field | OpenAI Source | Claude Source | Gemini Source |
|--------------|--------------|--------------|---------------|
| `message` | `choices[0].message.content` | `content[0].text` (text blocks) | `candidates[0].content.parts[0].text` |
| `usage.prompt_tokens` | `usage.prompt_tokens` | `usage.input_tokens` | `usageMetadata.promptTokenCount` |
| `usage.completion_tokens` | `usage.completion_tokens` | `usage.output_tokens` | `usageMetadata.candidatesTokenCount` |
| `finish_reason` | `choices[0].finish_reason` | `stop_reason` (mapped) | `candidates[0].finishReason` (mapped) |
| `tool_calls` | `choices[0].message.tool_calls` | `content[].tool_use` blocks | `candidates[0].content.parts[].functionCall` |

**Finish reason normalization**:

| Canonical | OpenAI | Claude | Gemini |
|-----------|--------|--------|--------|
| `stop` | `stop` | `end_turn` | `STOP` |
| `length` | `length` | `max_tokens` | `MAX_TOKENS` |
| `tool_calls` | `tool_calls` | `tool_use` | `TOOL_CODE` |
| `content_filter` | `content_filter` | N/A | `SAFETY` |

### 4.6 Streaming Chunk Format

Each adapter implementing `Streamable_Provider_Interface` MUST parse provider-specific SSE chunks and emit normalized chunks via the callback. See `streaming_transport_layer.md` for the full chunk normalization contract.

### 4.7 Error Handling

Each adapter MUST:
1. Classify errors using the 4-tier taxonomy (see `error_handling_fallback_contract.md`)
2. Detect retryable conditions by HTTP status code
3. Implement exponential backoff for retryable errors
4. Include `user_message` for all error responses (integrate Baachal pattern)
5. Never expose API keys, internal paths, or raw stack traces in error payloads

### 4.8 Timeout Values

| Provider | Connect Timeout | Request Timeout | Stream Timeout |
|----------|----------------|-----------------|----------------|
| All | 5 seconds | `$config['timeout']` (default 30s) | 120 seconds |

Timeouts are enforced via `wp_remote_post()` args or cURL options. Stream timeout covers the entire SSE connection lifetime.

### 4.9 Testability Requirements

Each adapter MUST be testable with:
1. **Unit tests**: Mock HTTP responses via `pre_http_request` filter or dependency injection
2. **Interface compliance test**: Registry validation passes (CR-01 through CR-05)
3. **Response normalization test**: Given a known provider response JSON, output matches unified schema
4. **Error handling test**: Given error HTTP responses (401, 403, 429, 500, 503), output matches error taxonomy
5. **Cost calculation test**: Given known token counts and model, cost matches expected microcents

### 4.10 Per-Provider Special Requirements

| Provider | Special Requirement |
|----------|-------------------|
| OpenAI | Dual-API routing (Chat Completions vs Responses API based on model) |
| Claude | `anthropic-version` header, system prompt as top-level param (not message), content blocks (not plain string) |
| Gemini | Model ID in URL path, `contents[]` with `parts[]` format, `systemInstruction` separate field, thinking mode for pro models |
| DeepSeek | OpenAI-compatible API format, `deepseek-` model prefix detection |
| Grok | OpenAI-compatible API format, `grok-` model prefix detection |

---

## 5. Testability Notes

### Unit Testable

- Interface compliance validation (registry checks CR-01 through CR-05)
- Constructor config validation (required keys, defaults)
- `get_id()` return value format
- `calculate_cost()` arithmetic (given known usage + model pricing)
- Response normalization (given mock provider responses)

### Integration Testable

- Registry registration via `wpaic_register_providers` hook
- Provider instantiation via registry `get()` with config injection
- Streaming callback invocation sequence
- Error -> retry -> fallback chain flow

### Not Testable in Isolation

- Actual API calls (require live API keys, covered by manual testing)
- SSE browser consumption (covered by session 04 runtime tests)

---
