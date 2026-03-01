# Error Handling and Fallback Contract

**Session**: `phase02-session03-provider-orchestration-and-model-registry-contracts`
**Version**: 1.0
**Created**: 2026-03-01
**Resolves**: CFI-PRO-002 (Generalized retry/fallback with user-friendly error messages)

---

## 1. Overview

This document defines the unified error handling, retry/backoff, circuit breaker, and fallback chain contracts for all provider adapters. The design generalizes AxiaChat's Claude adapter retry/backoff pattern to all adapters and integrates Baachal's user-friendly error messaging into every error response.

### Reference Anchors

| Source Plugin | File | Relevance |
|---------------|------|-----------|
| AxiaChat | `axiachat-ai/includes/providers/class-claude-provider.php` | Primary: Exponential backoff (1s/2s/4s), 3 max retries, retryable codes (429/503/529), non-retryable fast-fail (401/403/400), fallback chain logic |
| AxiaChat | `axiachat-ai/includes/providers/class-openai-provider.php` | Retry logic (429/503), 3 max retries, exponential backoff |
| MxChat | `mxchat-basic/includes/class-mxchat-integrator.php` | Basic error handling (HTTP status check, generic error messages), N/A for retry/fallback |
| Baachal | `baachal/baachal.php` | User-friendly error messages (quota exceeded, API not configured, overload), contextual hints for admin configuration |

---

## 2. Error Taxonomy

All provider errors are classified into a 4-tier taxonomy. Each tier has consistent handling semantics across all adapters.

### Tier 1: Network/Transport Errors

Errors at the HTTP transport layer, before the provider processes the request.

| Code | Condition | Retryable | User Message |
|------|-----------|-----------|-------------|
| 0 | Connection refused / DNS failure | Yes | "Unable to reach the AI service. Please try again in a moment." |
| 0 | Connection timeout (5s connect) | Yes | "The AI service is taking too long to respond. Please try again." |
| 0 | SSL/TLS error | No | "A secure connection to the AI service could not be established." |
| 0 | cURL error (generic) | Yes | "A temporary connection issue occurred. Please try again." |

**Type**: `network`

### Tier 2: Authentication/Authorization Errors

Errors indicating invalid or missing credentials.

| Code | Condition | Retryable | User Message |
|------|-----------|-----------|-------------|
| 401 | Invalid API key | No | "The AI service is not properly configured. Please contact the site administrator." |
| 403 | Insufficient permissions / IP blocked | No | "Access to the AI service was denied. Please contact the site administrator." |

**Type**: `authentication`

**Note**: Auth errors affect ALL models for a provider. Fallback to another model on the same provider is pointless. The error surfaces immediately.

### Tier 3: Rate Limit/Quota Errors

Errors indicating usage limits or capacity constraints.

| Code | Condition | Retryable | User Message |
|------|-----------|-----------|-------------|
| 429 | Rate limited (per-minute/per-day) | Yes | "The AI service is currently busy. Your request will be retried automatically." |
| 503 | Service temporarily unavailable | Yes | "The AI service is temporarily unavailable. Please try again in a moment." |
| 529 | Anthropic overloaded | Yes | "The AI service is experiencing high demand. Please try again shortly." |

**Type**: `rate_limit`

### Tier 4: Model/Content Errors

Errors related to the specific request content, model behavior, or provider-side processing.

| Code | Condition | Retryable | User Message |
|------|-----------|-----------|-------------|
| 400 | Bad request (malformed payload) | No | "There was a problem with the request. Please try rephrasing your message." |
| 404 | Model not found | No (fallback) | "The requested AI model is not available. Trying an alternative." |
| 413 | Request too large (token limit) | No | "Your message is too long. Please shorten it and try again." |
| 500 | Provider internal server error | Yes | "The AI service encountered an internal error. Please try again." |
| 502 | Bad gateway | Yes | "The AI service is temporarily unreachable. Please try again." |

**Type**: `model_error`

---

## 3. Standard Error Payload

Every error returned by a provider adapter uses this structure:

```php
array(
    'error' => array(
        'code'         => 429,                    // int: HTTP status code (0 for network errors)
        'type'         => 'rate_limit',            // string: Taxonomy tier type
        'message'      => 'Rate limited by OpenAI. Retry-After: 30s.',  // string: Technical message (for logging)
        'retryable'    => true,                    // bool: Whether retry is appropriate
        'user_message' => 'The AI service is currently busy. Your request will be retried automatically.',  // string: User-facing message
    ),
    'provider_id' => 'openai',                     // string: Provider that produced the error
    'model_id'    => 'gpt-4o',                     // string: Model that was attempted
    'request_id'  => 'req_abc123',                 // string: Request UUID for correlation
)
```

### Error Payload Rules

| Rule | Description |
|------|-------------|
| EP-01 | `code` MUST be an integer (HTTP status or 0 for transport errors) |
| EP-02 | `type` MUST be one of: `network`, `authentication`, `rate_limit`, `model_error` |
| EP-03 | `message` MAY contain diagnostic details but MUST NOT contain API keys, auth tokens, or file paths |
| EP-04 | `retryable` MUST accurately reflect whether the same request could succeed on retry |
| EP-05 | `user_message` MUST be safe for display in the chat widget (no technical jargon, no secrets) |
| EP-06 | `provider_id` and `model_id` SHOULD be included whenever known |
| EP-07 | `request_id` SHOULD be echoed from the request metadata for correlation |

### User Message Generation

User messages follow the Baachal pattern of providing helpful, non-technical guidance:

```php
/**
 * Generate a user-friendly error message.
 *
 * Translatable via wpaic_error_user_message filter.
 *
 * @param int    $code     HTTP status code.
 * @param string $type     Error taxonomy type.
 * @param string $provider Provider ID for context.
 *
 * @return string User-friendly message.
 */
function wpaic_get_user_error_message( $code, $type, $provider = '' );
```

User messages are filterable for customization:

```php
$user_message = apply_filters( 'wpaic_error_user_message', $user_message, $code, $type, $provider );
```

---

## 4. Retry and Backoff Contract

### Retry Policy

Generalized from AxiaChat's Claude adapter pattern, applied uniformly to all adapters:

| Parameter | Default | Option Key | Filter |
|-----------|---------|-----------|--------|
| Max attempts | 3 | `wpaic_provider_max_retries` | `wpaic_retry_policy` |
| Base delay | 1 second | - | `wpaic_retry_policy` |
| Backoff multiplier | 2 (exponential) | - | `wpaic_retry_policy` |
| Max delay | 16 seconds | - | `wpaic_retry_policy` |

### Exponential Backoff Formula

```
delay = min( base_delay * pow( multiplier, attempt - 1 ), max_delay )

Attempt 1: min(1 * 2^0, 16) = 1 second
Attempt 2: min(1 * 2^1, 16) = 2 seconds
Attempt 3: min(1 * 2^2, 16) = 4 seconds
```

### Retry Decision Matrix

```php
/**
 * Determine if a request should be retried.
 *
 * @param int $http_code   HTTP response status code.
 * @param int $attempt     Current attempt number (1-based).
 * @param int $max_retries Maximum retry attempts.
 *
 * @return bool True if the request should be retried.
 */
function wpaic_should_retry( $http_code, $attempt, $max_retries );
```

| HTTP Code | Retryable | Rationale |
|-----------|-----------|-----------|
| 0 (network) | Yes | Transient network issues |
| 429 | Yes | Rate limit, will clear after delay |
| 500 | Yes | Transient server error |
| 502 | Yes | Transient gateway error |
| 503 | Yes | Temporary unavailability |
| 529 | Yes | Anthropic overload (temporary) |
| 400 | No | Client error, won't fix on retry |
| 401 | No | Auth error, needs config change |
| 403 | No | Permission error, needs config change |
| 404 | No | Model not found, needs fallback |
| 413 | No | Payload too large, needs modification |
| All others | No | Unknown errors fast-fail |

### Retry Flow

```
chat($messages, $params)
  |
  +-> Attempt 1: call provider API
  |     |
  |     +-> Success: return response
  |     +-> Non-retryable error: return error immediately
  |     +-> Retryable error + attempts remaining:
  |           sleep(1 second)
  |           |
  +-> Attempt 2: call provider API
  |     |
  |     +-> Success: return response
  |     +-> Non-retryable error: return error immediately
  |     +-> Retryable error + attempts remaining:
  |           sleep(2 seconds)
  |           |
  +-> Attempt 3: call provider API
  |     |
  |     +-> Success: return response
  |     +-> Any error: return error (retry exhausted)
  |
  +-> (If retry exhausted and fallback available, see Section 5)
```

### Retry Policy Filter

```php
/**
 * Filter the retry policy for customization.
 *
 * @param array  $policy {
 *     @type int   $max_retries Maximum retry attempts.
 *     @type float $base_delay  Base delay in seconds.
 *     @type int   $multiplier  Backoff multiplier.
 *     @type int   $max_delay   Maximum delay in seconds.
 * }
 * @param string $provider_id Provider being retried.
 * @param int    $http_code   HTTP status code that triggered retry.
 */
$policy = apply_filters( 'wpaic_retry_policy', $policy, $provider_id, $http_code );
```

### Retry-After Header Respect

When a provider returns a `Retry-After` header (common with 429 responses), the adapter SHOULD respect it:

```php
$retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );
if ( $retry_after > 0 && $retry_after <= 60 ) {
    $delay = $retry_after;
} else {
    $delay = $base_delay * pow( $multiplier, $attempt - 1 );
}
```

The `Retry-After` value is capped at 60 seconds to prevent unreasonable waits.

---

## 5. Fallback Chain Orchestration

When all retry attempts are exhausted for a model, the orchestration layer engages the fallback chain (see `model_registry_data_model.md` Section 5).

### Fallback Flow

```
Orchestration Layer:
  |
  +-> Try primary model (with retries)
  |     +-> Success: return response
  |     +-> Retry exhausted on retryable error:
  |           |
  +-> Get remaining fallbacks: wpaic_get_remaining_fallbacks($provider, $model)
  |     +-> Empty: return terminal error
  |     +-> Non-empty: try next model
  |           |
  +-> Try fallback model 1 (with retries)
  |     +-> Success: return response (model_id reflects fallback)
  |     +-> Retry exhausted:
  |           |
  +-> Try fallback model 2 (with retries)
  |     +-> Success: return response
  |     +-> Retry exhausted:
  |           |
  +-> Chain exhausted: return terminal error
```

### Fallback Eligibility

Not all errors should trigger fallback (see `model_registry_data_model.md` Section 5 Fallback Decision Matrix):

| Error Type | Fallback? | Reason |
|------------|-----------|--------|
| Rate limit (429) | Yes | Different model may have separate limits |
| Service overload (503/529) | Yes | May affect specific models |
| Model not found (404) | Yes (skip retry) | Model-specific, try next |
| Network error (0) | Yes | Transient, different model endpoint may work |
| Timeout | Yes | Model-specific latency |
| Auth error (401/403) | No | Affects all models for provider |
| Bad request (400) | No | Request problem, not model |
| Content filter | No | Content problem, not model |

### Orchestration Function

```php
/**
 * Execute a chat request with retry and fallback orchestration.
 *
 * This is the top-level dispatch function used by the runtime.
 * It handles model resolution, retry, fallback, and cost tracking.
 *
 * @param Provider_Interface $adapter  Provider adapter instance.
 * @param array              $messages Normalized messages.
 * @param array              $params   Request parameters.
 *
 * @return array Unified response (success or terminal error).
 */
function wpaic_orchestrate_chat( $adapter, $messages, $params );
```

### Terminal Error (Chain Exhausted)

When all models in the fallback chain have failed:

```php
array(
    'error' => array(
        'code'         => 503,
        'type'         => 'model_error',
        'message'      => sprintf(
            'All models exhausted for provider %s. Attempted: %s.',
            $provider_id,
            implode( ', ', $attempted_models )
        ),
        'retryable'    => false,
        'user_message' => 'The AI service is temporarily unavailable. Please try again in a few minutes.',
    ),
    'provider_id' => $provider_id,
    'request_id'  => $request_id,
)
```

---

## 6. Circuit Breaker Semantics

Optional circuit breaker pattern to prevent cascading failures when a provider is consistently failing.

### Circuit Breaker States

```
CLOSED (normal operation)
  |
  +-> N failures in window --> OPEN (fast-fail)
                                  |
                                  +-> Cooldown expires --> HALF-OPEN (probe)
                                                            |
                                                            +-> Probe succeeds --> CLOSED
                                                            +-> Probe fails --> OPEN
```

### Configuration

| Parameter | Default | Description |
|-----------|---------|-------------|
| `failure_threshold` | 5 | Failures before circuit opens |
| `failure_window` | 60 seconds | Time window for counting failures |
| `cooldown_period` | 30 seconds | Time before half-open probe |

### Storage

Circuit breaker state is stored in WordPress transients:

```php
// Transient key: wpaic_cb_{provider_id}
// Value: array(
//     'state'          => 'closed',  // 'closed', 'open', 'half_open'
//     'failure_count'  => 0,
//     'last_failure'   => 0,         // Unix timestamp
//     'opened_at'      => 0,         // Unix timestamp (when circuit opened)
// )
```

### Circuit Breaker Check

```php
/**
 * Check if a provider's circuit breaker allows requests.
 *
 * @param string $provider_id Provider ID.
 *
 * @return bool True if requests are allowed (circuit closed or half-open probe).
 */
function wpaic_circuit_breaker_allows( $provider_id );

/**
 * Record a provider request result for circuit breaker tracking.
 *
 * @param string $provider_id Provider ID.
 * @param bool   $success     Whether the request succeeded.
 */
function wpaic_circuit_breaker_record( $provider_id, $success );
```

### Circuit Breaker Integration

The circuit breaker is checked BEFORE attempting a request:

```
wpaic_orchestrate_chat():
  |
  +-> Check circuit breaker: wpaic_circuit_breaker_allows($provider_id)
  |     +-> OPEN: return circuit-open error immediately
  |     +-> CLOSED/HALF-OPEN: proceed with request
  |
  +-> Execute request (with retry)
  |
  +-> Record result: wpaic_circuit_breaker_record($provider_id, $success)
```

### Circuit Breaker Error Message

```php
array(
    'error' => array(
        'code'         => 503,
        'type'         => 'rate_limit',
        'message'      => "Circuit breaker open for provider '{$provider_id}'. Too many failures in the last {$window}s.",
        'retryable'    => false,
        'user_message' => 'The AI service is experiencing issues. Please try again in a minute.',
    ),
)
```

### Circuit Breaker Filter

```php
/**
 * Filter circuit breaker configuration per provider.
 *
 * @param array  $config      { failure_threshold, failure_window, cooldown_period }
 * @param string $provider_id Provider ID.
 */
$config = apply_filters( 'wpaic_circuit_breaker_config', $config, $provider_id );
```

---

## 7. Logging Contract

All error handling events are logged via the structured logger (`lib/structured-logger.php`):

| Event | Log Level | Fields |
|-------|-----------|--------|
| Retry attempt | `warning` | provider_id, model_id, attempt, max_retries, http_code, delay_seconds |
| Retry exhausted | `error` | provider_id, model_id, total_attempts, last_error_code |
| Fallback engaged | `warning` | provider_id, failed_model, fallback_model, reason |
| Fallback exhausted | `error` | provider_id, models_attempted, last_error |
| Circuit breaker opened | `error` | provider_id, failure_count, window_seconds |
| Circuit breaker half-open probe | `info` | provider_id |
| Circuit breaker closed (recovery) | `info` | provider_id, downtime_seconds |

### Log Entry Shape

```php
wpaic_log( 'warning', 'provider.retry', array(
    'provider_id'   => 'openai',
    'model_id'      => 'gpt-4o',
    'attempt'       => 2,
    'max_retries'   => 3,
    'http_code'     => 429,
    'delay_seconds' => 2,
    'request_id'    => 'req_abc123',
) );
```

**Security**: Log entries MUST NOT contain API keys, auth tokens, or raw request/response bodies. Only status codes, model IDs, and timing information.

---

## 8. Hook Reference

| Hook | Type | Purpose | Parameters |
|------|------|---------|------------|
| `wpaic_retry_policy` | Filter | Customize retry parameters per provider | `array $policy, string $provider_id, int $http_code` |
| `wpaic_error_user_message` | Filter | Customize user-facing error messages | `string $message, int $code, string $type, string $provider_id` |
| `wpaic_circuit_breaker_config` | Filter | Customize circuit breaker parameters | `array $config, string $provider_id` |
| `wpaic_before_retry` | Action | Fired before each retry attempt | `string $provider_id, int $attempt, int $delay` |
| `wpaic_after_fallback` | Action | Fired after fallback model selected | `string $provider_id, string $failed_model, string $fallback_model` |
| `wpaic_chain_exhausted` | Action | Fired when all fallback models fail | `string $provider_id, array $attempted_models` |

---

## 9. Testability Notes

### Unit Testable

- Error classification by HTTP status code
- Retry decision logic (`wpaic_should_retry()`)
- Backoff delay calculation (exponential formula)
- User message generation per error type
- Fallback eligibility per error type
- Circuit breaker state transitions (closed -> open -> half-open -> closed/open)

### Integration Testable

- Full retry flow (mock HTTP responses, verify attempt count and delays)
- Fallback chain flow (mock sequential model failures, verify chain traversal)
- Circuit breaker with WordPress transients (verify state persistence)
- Logging output verification (structured log entries)
- Filter customization (wpaic_retry_policy, wpaic_error_user_message)

### Edge Cases to Test

- Retry with `Retry-After` header (respect vs. cap at 60s)
- Fallback with mixed error types (retryable model A -> non-retryable model B -> skip)
- Circuit breaker race conditions (concurrent requests during half-open)
- Zero retries configured (immediate failure, engage fallback)
- Empty fallback chain (immediate terminal error)
- Network error during retry sleep (PHP process killed)

---
