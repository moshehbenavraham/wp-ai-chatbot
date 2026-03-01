# Model Registry Data Model

**Session**: `phase02-session03-provider-orchestration-and-model-registry-contracts`
**Version**: 1.0
**Created**: 2026-03-01

---

## 1. Overview

The Model Registry is an in-memory PHP array that serves as the single source of truth for all model metadata: capabilities, pricing, aliases, deprecation status, and fallback chains. It is static per request (built once, cached), filterable for extensibility, and consumed by the provider registry, cost accounting, admin UI, and runtime model selection.

Design adopted from AxiaChat's `model-registry.php` with enhancements for deprecation handling, alias resolution, and structured fallback chains.

### Reference Anchors

| Source Plugin | File | Relevance |
|---------------|------|-----------|
| AxiaChat | `axiachat-ai/includes/model-registry.php` | Primary: Full model catalog with pricing, aliases, deprecation, fallback_order, capabilities; resolution pipeline (exact, alias, prefix, default); JS payload builder |
| MxChat | `mxchat-basic/includes/class-mxchat-admin.php` | Model list arrays per provider (flat string arrays, no metadata, no pricing) |
| Baachal | `baachal/admin/tabs/general.php` | Model dropdown options (flat string arrays, no metadata, no pricing) |

---

## 2. Data Model

### Model Entry Structure

Each model in the registry is a PHP associative array with these fields:

```php
array(
    // Identity
    'id'                => 'gpt-4o',              // string: Canonical model ID (unique across all providers)
    'provider'          => 'openai',              // string: Canonical provider ID (matches Provider_Registry)
    'label'             => 'GPT-4o',              // string: Human-readable display name

    // Capabilities
    'context_window'    => 128000,                // int: Maximum input tokens
    'max_output'        => 16384,                 // int: Maximum output tokens
    'supports_thinking' => false,                 // bool: Extended thinking/reasoning mode
    'multimodal'        => true,                  // bool: Accepts image/audio input
    'supports_tools'    => true,                  // bool: Function calling support
    'supports_streaming'=> true,                  // bool: SSE streaming support

    // Pricing (per 1 million tokens, in USD)
    'pricing'           => array(
        'input'  => 2.50,                         // float|null: Input cost per 1M tokens (null = free)
        'output' => 10.00,                        // float|null: Output cost per 1M tokens (null = free)
    ),

    // Aliases and Deprecation
    'aliases'           => array( 'gpt4o' ),      // array: Alternative IDs that resolve to this model
    'deprecated_ids'    => array(),                // array: Former IDs this model replaces
    'is_deprecated'     => false,                  // bool: Whether this model is deprecated
    'deprecation_note'  => '',                     // string: Deprecation reason/migration guidance

    // Defaults and Fallback
    'is_default'        => true,                   // bool: Default model for this provider
    'fallback_order'    => 1,                      // int: Position in fallback chain (lower = tried first, 0 = not in chain)

    // Tags (for filtering and categorization)
    'tags'              => array( 'chat', 'fast' ), // array: Capability tags
)
```

### Field Reference

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `id` | string | Yes | - | Canonical model ID. Must be unique across all providers. |
| `provider` | string | Yes | - | Canonical provider ID matching Provider_Registry. |
| `label` | string | Yes | - | Human-readable name for admin UI and display. |
| `context_window` | int | Yes | - | Maximum input context length in tokens. |
| `max_output` | int | Yes | - | Maximum response length in tokens. |
| `supports_thinking` | bool | No | `false` | Whether model supports extended thinking mode. |
| `multimodal` | bool | No | `false` | Whether model accepts non-text inputs. |
| `supports_tools` | bool | No | `false` | Whether model supports function/tool calling. |
| `supports_streaming` | bool | No | `true` | Whether model supports SSE streaming. |
| `pricing.input` | float/null | No | `null` | Input cost per 1M tokens in USD. Null = free/unknown. |
| `pricing.output` | float/null | No | `null` | Output cost per 1M tokens in USD. Null = free/unknown. |
| `aliases` | array | No | `array()` | Short names that resolve to this model's canonical ID. |
| `deprecated_ids` | array | No | `array()` | Old model IDs that this model replaces. |
| `is_deprecated` | bool | No | `false` | Whether this model is scheduled for removal. |
| `deprecation_note` | string | No | `''` | Human-readable deprecation guidance. |
| `is_default` | bool | No | `false` | Whether this is the default model for its provider. One per provider. |
| `fallback_order` | int | No | `0` | Position in provider fallback chain. 0 = not in chain. |
| `tags` | array | No | `array()` | Filtering tags (e.g., 'chat', 'fast', 'reasoning', 'vision'). |

---

## 3. Registry Function

### wpaic_get_model_registry()

```php
/**
 * Get the complete model registry.
 *
 * Returns a cached static array of all registered models. The registry
 * is built once per request and cached in a static variable. Third-party
 * plugins can extend the registry via the 'wpaic_model_registry' filter.
 *
 * @return array Model registry indexed by model ID.
 */
function wpaic_get_model_registry() {
    static $registry = null;

    if ( null !== $registry ) {
        return $registry;
    }

    $models = array(
        // OpenAI models
        'gpt-4o' => array( /* ... */ ),
        'gpt-4o-mini' => array( /* ... */ ),
        // Claude models
        'claude-sonnet-4-5' => array( /* ... */ ),
        'claude-haiku-3-5' => array( /* ... */ ),
        // Gemini models
        'gemini-2.5-pro' => array( /* ... */ ),
        'gemini-2.5-flash' => array( /* ... */ ),
        // DeepSeek models
        'deepseek-chat' => array( /* ... */ ),
        'deepseek-reasoner' => array( /* ... */ ),
        // Grok models
        'grok-3' => array( /* ... */ ),
        'grok-3-mini' => array( /* ... */ ),
    );

    /**
     * Filter the model registry.
     *
     * Allows third-party plugins and adapters to add, modify, or remove
     * models from the registry. Adapters should add their models during
     * this filter rather than modifying the core array.
     *
     * @param array $models Model registry indexed by model ID.
     */
    $registry = apply_filters( 'wpaic_model_registry', $models );

    return $registry;
}
```

### Helper Functions

```php
/**
 * Get all models for a specific provider.
 *
 * @param string $provider_id Canonical provider ID.
 * @return array Models for the provider, indexed by model ID.
 */
function wpaic_get_models_for_provider( $provider_id );

/**
 * Get a single model entry by ID.
 *
 * @param string $model_id Model ID (canonical, alias, or deprecated).
 * @return array|null Model entry or null if not found.
 */
function wpaic_get_model( $model_id );

/**
 * Get the default model for a provider.
 *
 * @param string $provider_id Canonical provider ID.
 * @return string|null Default model ID or null if no default set.
 */
function wpaic_get_default_model( $provider_id );

/**
 * Get model pricing data.
 *
 * @param string $model_id    Model ID.
 * @param string $provider_id Optional provider hint for disambiguation.
 * @return array|null Pricing array { input: float|null, output: float|null } or null.
 */
function wpaic_get_model_pricing( $model_id, $provider_id = '' );

/**
 * Build a flat alias-to-canonical lookup map.
 *
 * Combines aliases and deprecated_ids into a single flat map for
 * fast resolution. Cached in a static variable.
 *
 * @return array Alias map { alias_string => canonical_model_id }.
 */
function wpaic_build_alias_map();

/**
 * Build a JS-friendly registry payload for admin pages.
 *
 * Groups models by provider with display-relevant fields only.
 *
 * @return array {
 *     @type array $models   Models grouped by provider ID.
 *     @type array $defaults Default model per provider.
 * }
 */
function wpaic_registry_js_payload();
```

---

## 4. Model Resolution Pipeline

### wpaic_resolve_model()

A deterministic 6-step lookup that resolves any model identifier to a canonical model ID.

```php
/**
 * Resolve a model identifier to a canonical model ID.
 *
 * Follows a deterministic 6-step pipeline:
 * 1. Exact match in registry
 * 2. Alias match (from aliases arrays)
 * 3. Deprecated ID match (from deprecated_ids arrays)
 * 4. Prefix match (longest prefix wins, scoped to provider_hint)
 * 5. Provider default (is_default=true for the hinted provider)
 * 6. Global default (wpaic_provider_default_model option)
 *
 * @param string $model_id      Model identifier to resolve.
 * @param string $provider_hint Optional provider ID to scope resolution.
 *
 * @return string Resolved canonical model ID. Never returns empty --
 *                falls through to global default as last resort.
 */
function wpaic_resolve_model( $model_id, $provider_hint = '' );
```

### Resolution Steps

#### Step 1: Exact Match

```
Input: 'gpt-4o'
Registry lookup: isset($registry['gpt-4o']) === true
Result: 'gpt-4o' (resolved)
```

The fastest path. Model ID exists as a key in the registry array.

#### Step 2: Alias Match

```
Input: 'gpt4o'
Alias map lookup: $alias_map['gpt4o'] === 'gpt-4o'
Result: 'gpt-4o' (resolved via alias)
```

Uses the flat alias map from `wpaic_build_alias_map()`. Aliases are case-sensitive.

#### Step 3: Deprecated ID Match

```
Input: 'gpt-4-turbo-preview'
Alias map lookup (deprecated_ids are included): $alias_map['gpt-4-turbo-preview'] === 'gpt-4o'
Result: 'gpt-4o' (resolved via deprecated ID)
Log: "Model 'gpt-4-turbo-preview' is deprecated. Using 'gpt-4o' instead."
```

Deprecated ID matches emit a debug log notice to help administrators update their configurations.

#### Step 4: Prefix Match

```
Input: 'claude-sonnet-4-5-20250929' (snapshot ID not in registry)
Provider hint: 'claude'
Prefix scan: For each model where provider === 'claude',
             check if $model_id starts with $registry_model_id
Longest match wins: 'claude-sonnet-4-5' (15 chars) beats 'claude-' (7 chars)
Result: 'claude-sonnet-4-5' (resolved via prefix)
```

Prefix matching handles snapshot/dated model IDs that providers use for versioning. Scoped to `$provider_hint` if provided to avoid cross-provider false matches.

#### Step 5: Provider Default

```
Input: 'nonexistent-model'
Provider hint: 'openai'
Lookup: Find model where provider === 'openai' && is_default === true
Result: 'gpt-4o' (provider default)
Log: "Model 'nonexistent-model' not found. Using provider default 'gpt-4o'."
```

Falls through when no match found in steps 1-4. Requires a `$provider_hint`.

#### Step 6: Global Default

```
Input: 'nonexistent-model'
Provider hint: '' (empty)
Lookup: get_option('wpaic_provider_default_model', 'gpt-4o')
Result: 'gpt-4o' (global default)
Log: "Model 'nonexistent-model' not found. Using global default 'gpt-4o'."
```

Last resort. Returns the globally configured default model from wp_options (per session 02 options schema: `wpaic_provider_default_model` defaults to `'gpt-4o'`).

### Resolution Edge Cases

| Input | Provider Hint | Step | Result | Notes |
|-------|--------------|------|--------|-------|
| `'gpt-4o'` | any | 1 (exact) | `'gpt-4o'` | Direct match |
| `'gpt4o'` | any | 2 (alias) | `'gpt-4o'` | Alias defined in model entry |
| `'gpt-4-turbo-preview'` | any | 3 (deprecated) | `'gpt-4o'` | Deprecated ID, debug notice logged |
| `'claude-sonnet-4-5-20250929'` | `'claude'` | 4 (prefix) | `'claude-sonnet-4-5'` | Snapshot ID, longest prefix match |
| `'unknown-model'` | `'openai'` | 5 (provider default) | `'gpt-4o'` | No match, falls to provider default |
| `'unknown-model'` | `''` | 6 (global default) | `'gpt-4o'` | No match, no hint, falls to global |
| `''` (empty) | any | 5 or 6 | Provider/global default | Empty input treated as unspecified |
| `'GEMINI-2.5-PRO'` | any | Fail steps 1-4 | Step 5/6 | Case-sensitive; IDs must be lowercase |

### Provider Hint Inference

If `$provider_hint` is empty, the resolver attempts to infer the provider from model ID prefixes:

| Prefix Pattern | Inferred Provider |
|---------------|-------------------|
| `gpt-*`, `o1-*`, `o3-*` | `openai` |
| `claude-*` | `claude` |
| `gemini-*` | `gemini` |
| `deepseek-*` | `deepseek` |
| `grok-*` | `grok` |

This inference is a best-effort optimization and does not override an explicit `$provider_hint`.

---

## 5. Fallback Chain Contract

### wpaic_get_fallback_chain()

```php
/**
 * Get the ordered fallback chain for a provider.
 *
 * Returns an array of model IDs sorted by fallback_order (ascending).
 * Only includes models with fallback_order > 0. The primary model
 * (typically fallback_order=1) is first.
 *
 * @param string $provider_id Canonical provider ID.
 *
 * @return array Ordered array of model IDs. Empty array if no chain defined.
 */
function wpaic_get_fallback_chain( $provider_id );
```

### Fallback Chain Behavior

The fallback chain is used by the error handling layer when a model request fails with a retryable error and all retry attempts are exhausted. The orchestration layer then tries the next model in the chain.

**Chain construction**:

```php
// For provider 'claude':
// claude-sonnet-4-5:   fallback_order = 1 (primary)
// claude-haiku-3-5:    fallback_order = 2 (first fallback)
// claude-opus-4:       fallback_order = 0 (not in chain)

wpaic_get_fallback_chain('claude');
// Returns: array('claude-sonnet-4-5', 'claude-haiku-3-5')
```

**Skip-current logic**: When the failed model is in the chain, start from the model after it:

```php
/**
 * Get remaining fallback models after the current one.
 *
 * @param string $provider_id  Canonical provider ID.
 * @param string $current_model The model that just failed.
 *
 * @return array Remaining models in fallback order. Empty if exhausted.
 */
function wpaic_get_remaining_fallbacks( $provider_id, $current_model );
```

```
Chain: [A, B, C]
Current failed: B
Remaining: [C]

Chain: [A, B, C]
Current failed: A
Remaining: [B, C]

Chain: [A, B, C]
Current failed: C
Remaining: [] (exhausted)
```

### Chain Exhaustion Behavior

When all models in the fallback chain have been tried and failed:

1. Return a terminal error response (not retryable)
2. Include a user-friendly message (Baachal pattern):

```php
array(
    'error' => array(
        'code'         => 503,
        'type'         => 'model_error',
        'message'      => "All fallback models exhausted for provider '{$provider_id}'. Models tried: {$models_list}.",
        'retryable'    => false,
        'user_message' => 'The AI service is temporarily unavailable. Please try again in a few minutes.',
    ),
)
```

3. Log the full chain attempt sequence for diagnostics

### Fallback Filter

```php
/**
 * Filter the fallback chain for a provider.
 *
 * Allows customization of fallback order, chain membership, or
 * injection of custom fallback models.
 *
 * @param array  $chain       Ordered array of model IDs.
 * @param string $provider_id Provider ID.
 */
$chain = apply_filters( 'wpaic_fallback_chain', $chain, $provider_id );
```

### Fallback Decision Matrix

Not all errors should trigger a fallback. The fallback chain is engaged ONLY when:

| Error Type | Retry First? | Fallback After Retry Exhaustion? |
|------------|-------------|----------------------------------|
| Rate limit (429) | Yes (with backoff) | Yes |
| Service overload (503) | Yes (with backoff) | Yes |
| Anthropic overload (529) | Yes (with backoff) | Yes |
| Model not found (404) | No | Yes (skip to next model) |
| Auth error (401/403) | No | No (affects all models) |
| Bad request (400) | No | No (request problem, not model) |
| Network error | Yes | Yes |
| Content filter | No | No (content problem, not model) |
| Timeout | Yes (once) | Yes |

---

## 6. Default Model Catalog

The following models are included in the base registry. Actual model IDs, pricing, and capabilities will be finalized during Phase 04 adapter implementation.

### OpenAI

| Model ID | Label | Context | Max Output | Tools | Streaming | Default | Fallback |
|----------|-------|---------|------------|-------|-----------|---------|----------|
| `gpt-4o` | GPT-4o | 128K | 16K | Yes | Yes | Yes | 1 |
| `gpt-4o-mini` | GPT-4o Mini | 128K | 16K | Yes | Yes | No | 2 |
| `o1` | O1 | 200K | 100K | Yes | Yes | No | 0 |
| `o3-mini` | O3 Mini | 200K | 100K | Yes | Yes | No | 3 |

### Claude

| Model ID | Label | Context | Max Output | Tools | Streaming | Default | Fallback |
|----------|-------|---------|------------|-------|-----------|---------|----------|
| `claude-sonnet-4-5` | Claude Sonnet 4.5 | 200K | 16K | Yes | Yes | Yes | 1 |
| `claude-haiku-3-5` | Claude Haiku 3.5 | 200K | 8K | Yes | Yes | No | 2 |
| `claude-opus-4` | Claude Opus 4 | 200K | 32K | Yes | Yes | No | 0 |

### Gemini

| Model ID | Label | Context | Max Output | Tools | Streaming | Default | Fallback |
|----------|-------|---------|------------|-------|-----------|---------|----------|
| `gemini-2.5-pro` | Gemini 2.5 Pro | 1M | 65K | Yes | Yes | Yes | 1 |
| `gemini-2.5-flash` | Gemini 2.5 Flash | 1M | 65K | Yes | Yes | No | 2 |

### DeepSeek

| Model ID | Label | Context | Max Output | Tools | Streaming | Default | Fallback |
|----------|-------|---------|------------|-------|-----------|---------|----------|
| `deepseek-chat` | DeepSeek Chat | 64K | 8K | Yes | Yes | Yes | 1 |
| `deepseek-reasoner` | DeepSeek Reasoner | 64K | 8K | No | Yes | No | 2 |

### Grok

| Model ID | Label | Context | Max Output | Tools | Streaming | Default | Fallback |
|----------|-------|---------|------------|-------|-----------|---------|----------|
| `grok-3` | Grok 3 | 131K | 16K | Yes | Yes | Yes | 1 |
| `grok-3-mini` | Grok 3 Mini | 131K | 16K | Yes | Yes | No | 2 |

---

## 7. Concurrency Safety

The model registry uses a PHP static variable cached per request. Since PHP processes requests in isolation (no shared memory between requests), there are no concurrency races to guard against.

**Filter modification safety**: The `wpaic_model_registry` filter fires once during the first call to `wpaic_get_model_registry()`. Subsequent calls return the cached result. Multiple filter callbacks execute synchronously in WordPress hook priority order.

**Admin setting changes**: If an admin modifies provider/model settings during a request (e.g., via AJAX), the in-memory registry is NOT refreshed. The new settings take effect on the next request. This is safe because settings changes and chat requests are separate HTTP requests.

---

## 8. Testability Notes

### Unit Testable

- Model entry schema validation (required fields, types)
- Resolution pipeline: exact match, alias, deprecated ID, prefix, provider default, global default
- Fallback chain construction and ordering
- Skip-current logic and chain exhaustion
- Alias map construction
- JS payload builder output shape
- Edge cases: empty provider, unknown model, deprecated with no replacement

### Integration Testable

- `wpaic_model_registry` filter extensibility (add/remove/modify models)
- `wpaic_fallback_chain` filter customization
- Resolution with wp_options-backed global default
- Registry JS payload consumed by admin scripts

---
