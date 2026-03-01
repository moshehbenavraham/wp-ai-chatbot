# Cost Accounting Interface

**Session**: `phase02-session03-provider-orchestration-and-model-registry-contracts`
**Version**: 1.0
**Created**: 2026-03-01

---

## 1. Overview

This document defines the cost accounting interface for per-request metering and daily usage aggregation. It connects the `calculate_cost()` method on provider adapters to session 02's `wpaic_usage_daily` database table via a decoupled metering hook.

### Reference Anchors

| Source Plugin | File | Relevance |
|---------------|------|-----------|
| AxiaChat | `axiachat-ai/includes/providers/class-openai-provider.php` | Per-request cost calculation (per-1K pricing, microcents conversion) |
| AxiaChat | `axiachat-ai/includes/usage.php` | Usage tracking and aggregation functions |
| AxiaChat | `axiachat-ai/includes/model-registry.php` | Registry-driven pricing data (per-1M tokens) |
| MxChat | `mxchat-basic/includes/class-mxchat-integrator.php` | N/A -- no cost tracking |
| Baachal | `baachal/baachal.php` | N/A -- no cost tracking |

---

## 2. Pricing Model

### Units

| Unit | Definition | Example |
|------|-----------|---------|
| USD | US Dollars | $0.015 |
| Cent | 1/100 of a dollar | 1.5 cents |
| Microcent | 1/10,000 of a cent = 1/1,000,000 of a dollar | 15,000 microcents |

**All internal cost values use microcents (integer)** to avoid floating-point precision issues.

### Conversion Formula

```
cost_microcents = (tokens / 1,000,000) * rate_per_1M_usd * 100 * 10,000

Simplified:
cost_microcents = tokens * rate_per_1M_usd
```

**Derivation**:
```
tokens / 1,000,000  = fraction of 1M tokens used
* rate_per_1M_usd   = cost in USD
* 100                = cost in cents
* 10,000             = cost in microcents

Combined: tokens * rate / 1,000,000 * 1,000,000 = tokens * rate
```

**Example**: GPT-4o, 1,500 prompt tokens at $2.50/1M input:
```
input_cost  = 1500 * 2.50 = 3,750 microcents ($0.0000375)
```

### Registry Pricing Source

Cost calculation uses pricing data from the model registry (`model_registry_data_model.md`):

```php
$pricing = wpaic_get_model_pricing( $model_id, $provider_id );
// Returns: array( 'input' => 2.50, 'output' => 10.00 )
// Values are USD per 1 million tokens. null = free/unknown.
```

---

## 3. Per-Request Metering

### calculate_cost() Interface Method

Defined in `Provider_Interface` (see `provider_interface_contract.md`):

```php
/**
 * Calculate request cost in microcents.
 *
 * @param array  $usage { prompt_tokens: int, completion_tokens: int, total_tokens: int }
 * @param string $model The model ID used for the request.
 *
 * @return int|null Cost in microcents, or null if not calculable.
 */
public function calculate_cost( $usage, $model );
```

### Implementation Contract

Each adapter MUST implement `calculate_cost()` following these rules:

```php
public function calculate_cost( $usage, $model ) {
    $pricing = wpaic_get_model_pricing( $model, $this->get_id() );

    // Null pricing = free or unknown model
    if ( null === $pricing || ( null === $pricing['input'] && null === $pricing['output'] ) ) {
        return null;
    }

    $input_cost  = 0;
    $output_cost = 0;

    if ( null !== $pricing['input'] && isset( $usage['prompt_tokens'] ) ) {
        $input_cost = (int) round( $usage['prompt_tokens'] * $pricing['input'] );
    }

    if ( null !== $pricing['output'] && isset( $usage['completion_tokens'] ) ) {
        $output_cost = (int) round( $usage['completion_tokens'] * $pricing['output'] );
    }

    return $input_cost + $output_cost;
}
```

### Prefix Matching for Snapshot Models

When a model ID (e.g., `claude-sonnet-4-5-20250929`) is not directly in the registry, pricing lookup falls back to prefix matching via `wpaic_get_model_pricing()`:

```
Input: 'claude-sonnet-4-5-20250929'
No exact match in pricing
Prefix match: 'claude-sonnet-4-5' (longest match)
Use pricing from 'claude-sonnet-4-5' entry
```

### Null Handling for Free/Local Models

| Scenario | `calculate_cost()` Return | Usage Tracking |
|----------|--------------------------|----------------|
| Model has pricing data | Integer microcents | Tracked with cost |
| Model pricing is null (free tier) | `null` | Tracked with cost = 0 |
| Model not in registry | `null` | Tracked with cost = 0 |
| Usage data missing tokens | `null` | Not tracked |

---

## 4. Daily Usage Aggregation

### wpaic_usage_daily Table (Session 02 Reference)

From `database_table_schemas.md`:

```sql
CREATE TABLE {$prefix}wpaic_usage_daily (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usage_date         DATE            NOT NULL,
    provider           VARCHAR(32)     NOT NULL DEFAULT '',
    model              VARCHAR(64)     NOT NULL DEFAULT '',
    prompt_tokens      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    completion_tokens  BIGINT UNSIGNED NOT NULL DEFAULT 0,
    total_cost         BIGINT UNSIGNED NOT NULL DEFAULT 0,
    conversation_count INT UNSIGNED    NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY date_provider_model (usage_date, provider, model)
);
```

**Column mapping from response to table**:

| Response Field | Table Column | Notes |
|---------------|-------------|-------|
| `provider_id` | `provider` | Canonical provider ID |
| `model_id` | `model` | Actual model used (post-fallback) |
| `usage.prompt_tokens` | `prompt_tokens` | Accumulated daily |
| `usage.completion_tokens` | `completion_tokens` | Accumulated daily |
| `calculate_cost()` return | `total_cost` | Accumulated daily (microcents) |
| (implicit) | `conversation_count` | Incremented per request |
| `current_date()` | `usage_date` | Server-local date |

### wpaic_track_usage() Function

```php
/**
 * Track usage for a completed provider request.
 *
 * Performs an UPSERT against the wpaic_usage_daily table:
 * - If a row exists for (date, provider, model): UPDATE by adding tokens/cost
 * - If no row exists: INSERT with the request's values
 *
 * Uses MySQL INSERT ... ON DUPLICATE KEY UPDATE for atomic upsert.
 *
 * @param array $data {
 *     Usage data from a completed request.
 *
 *     @type string $provider_id       Canonical provider ID.
 *     @type string $model_id          Actual model ID used.
 *     @type int    $prompt_tokens     Input tokens consumed.
 *     @type int    $completion_tokens Output tokens generated.
 *     @type int    $cost              Cost in microcents (0 if null from calculate_cost).
 * }
 *
 * @return bool True if tracking succeeded, false on database error.
 */
function wpaic_track_usage( $data );
```

### UPSERT SQL Pattern

```php
$wpdb->query( $wpdb->prepare(
    "INSERT INTO {$table} (usage_date, provider, model, prompt_tokens, completion_tokens, total_cost, conversation_count)
     VALUES (%s, %s, %s, %d, %d, %d, 1)
     ON DUPLICATE KEY UPDATE
       prompt_tokens = prompt_tokens + VALUES(prompt_tokens),
       completion_tokens = completion_tokens + VALUES(completion_tokens),
       total_cost = total_cost + VALUES(total_cost),
       conversation_count = conversation_count + 1",
    current_time( 'Y-m-d' ),
    $data['provider_id'],
    $data['model_id'],
    $data['prompt_tokens'],
    $data['completion_tokens'],
    $data['cost']
) );
```

The UNIQUE KEY `date_provider_model` on `(usage_date, provider, model)` enables the `ON DUPLICATE KEY UPDATE` pattern. No schema changes are required from session 02.

---

## 5. Metering Hook

Usage tracking is decoupled from the request flow via a WordPress action hook, following the Observer pattern.

### wpaic_after_provider_request Action

```php
/**
 * Fired after a provider request completes (success or error).
 *
 * Consumers can hook into this for usage tracking, analytics,
 * audit logging, or custom metering.
 *
 * @param array $response Unified response payload (success or error).
 * @param array $request  Original request payload.
 * @param int|null $cost  Cost in microcents from calculate_cost(), or null.
 */
do_action( 'wpaic_after_provider_request', $response, $request, $cost );
```

### Default Hook Handler

The plugin registers a default handler for usage tracking:

```php
add_action( 'wpaic_after_provider_request', 'wpaic_handle_usage_tracking', 10, 3 );

/**
 * Default usage tracking handler.
 *
 * Tracks successful requests in the wpaic_usage_daily table.
 * Skips tracking for error responses.
 *
 * @param array    $response Unified response.
 * @param array    $request  Original request.
 * @param int|null $cost     Cost in microcents.
 */
function wpaic_handle_usage_tracking( $response, $request, $cost ) {
    // Skip error responses
    if ( isset( $response['error'] ) ) {
        return;
    }

    // Skip if usage data is missing
    if ( ! isset( $response['usage'] ) ) {
        return;
    }

    wpaic_track_usage( array(
        'provider_id'       => $response['provider_id'],
        'model_id'          => $response['model_id'],
        'prompt_tokens'     => $response['usage']['prompt_tokens'],
        'completion_tokens' => $response['usage']['completion_tokens'],
        'cost'              => $cost ?? 0,
    ) );
}
```

### Streaming Usage Tracking

For streaming requests, the `wpaic_after_provider_request` action fires AFTER `stream_chat()` returns the summary array. The summary contains the final accumulated usage data:

```
stream_chat() returns summary
  |
  +-> calculate_cost( summary['usage'], summary['model_id'] )
  +-> do_action( 'wpaic_after_provider_request', summary, request, cost )
```

---

## 6. Usage Query Functions

For admin dashboard and analytics:

```php
/**
 * Get daily usage summary for a date range.
 *
 * @param string $start_date Start date (Y-m-d).
 * @param string $end_date   End date (Y-m-d).
 * @param string $provider   Optional provider filter.
 *
 * @return array Array of usage rows.
 */
function wpaic_get_usage_summary( $start_date, $end_date, $provider = '' );

/**
 * Get total cost for the current billing period.
 *
 * @param string $period 'day', 'week', 'month'.
 *
 * @return int Total cost in microcents.
 */
function wpaic_get_period_cost( $period = 'month' );

/**
 * Format microcents as human-readable currency string.
 *
 * @param int    $microcents Cost in microcents.
 * @param string $currency   Currency code (default: 'USD').
 *
 * @return string Formatted string (e.g., "$1.23").
 */
function wpaic_format_cost( $microcents, $currency = 'USD' );
```

### Format Examples

```php
wpaic_format_cost( 0 );          // "$0.00"
wpaic_format_cost( 1 );          // "$0.000001" (1 microcent)
wpaic_format_cost( 10000 );      // "$0.0001"   (1 cent = 10,000 microcents? No.)
// Correction: 1 microcent = 1/10,000 cent = 1/1,000,000 dollar
// 10,000 microcents = 1 cent = $0.01
wpaic_format_cost( 10000 );      // "$0.01"
wpaic_format_cost( 1000000 );    // "$1.00"
wpaic_format_cost( 1500000 );    // "$1.50"
wpaic_format_cost( 3750 );       // "$0.004" (rounded to 3 decimal places for sub-cent)
```

Display rules:
- Costs >= $0.01: 2 decimal places (`$1.23`)
- Costs < $0.01 and > $0: up to 4 decimal places (`$0.0038`)
- Cost = 0: `$0.00`

---

## 7. Hook Reference

| Hook | Type | Purpose | Parameters |
|------|------|---------|------------|
| `wpaic_after_provider_request` | Action | Decoupled metering point | `array $response, array $request, int\|null $cost` |
| `wpaic_usage_tracked` | Action | Fired after successful DB insert/update | `array $usage_data` |
| `wpaic_calculate_cost` | Filter | Override cost calculation | `int\|null $cost, array $usage, string $model, string $provider` |

The `wpaic_calculate_cost` filter allows plugins to override pricing (e.g., for custom/proxy providers with different rates):

```php
$cost = apply_filters( 'wpaic_calculate_cost', $cost, $usage, $model, $provider_id );
```

---

## 8. Testability Notes

### Unit Testable

- Cost calculation arithmetic (microcent conversion, rounding)
- Null handling (free models, missing pricing, missing usage)
- Prefix matching for snapshot model pricing
- Cost formatting (wpaic_format_cost)
- Usage tracking data validation

### Integration Testable

- UPSERT against wpaic_usage_daily table (insert new row, update existing)
- Hook firing sequence (wpaic_after_provider_request -> wpaic_track_usage -> wpaic_usage_tracked)
- Usage query functions (wpaic_get_usage_summary, wpaic_get_period_cost)
- Filter override (wpaic_calculate_cost)
- Streaming usage tracking (summary -> cost -> track)

### Edge Cases to Test

- Zero-token response (usage = {0, 0, 0}): cost = 0, tracked
- Null pricing model: cost = null, tracked as 0
- Very large token counts (1M+ tokens): verify no integer overflow in microcents
- Concurrent usage tracking (two requests for same date/provider/model): UPSERT handles correctly
- Date boundary (request starts at 23:59, completes at 00:01): uses completion timestamp date

---
